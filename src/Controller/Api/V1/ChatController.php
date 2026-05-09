<?php
declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Integration\Llm\LlmClientFactory;
use App\Integration\Llm\LlmMessage;
use App\Integration\Llm\OpenAiClient;
use App\Messaging\Exception\HandoffStateException;
use App\Messaging\Service\MessageDispatcher;
use App\Model\Entity\Agent;
use App\Model\Entity\ChatSession;
use App\Model\Entity\User;
use App\Service\AgentLogService;
use App\Service\AgentTools\AgentLoopService;
use App\Service\AgentTools\GitHubToolProvider;
use App\Service\ChatSessionService;
use App\Service\LlmService;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use DevOpsOrchestrator\Integration\GitHub\GitHubClient;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * REST API controller for the agent chat feature.
 *
 * Manages chat sessions (CRUD) and handles the message-send endpoint which
 * streams the LLM response back to the browser via Server-Sent Events (SSE).
 * All state is persisted to chat_sessions and chat_messages so conversations
 * can be resumed across page loads.
 *
 * The message() action bypasses CakePHP's normal response pipeline to emit
 * raw SSE output. It disables output buffering, sets the required headers,
 * then streams LLM deltas directly to the client. The assistant message is
 * saved to the database only after the stream completes successfully.
 */
class ChatController extends AppController
{
    private ChatSessionService $chatSessionService;
    private LlmService $llmService;
    private MessageDispatcher $dispatcher;

    public function initialize(): void
    {
        parent::initialize();
        $logService = new AgentLogService();
        $this->chatSessionService = new ChatSessionService();
        $this->llmService = new LlmService(new LlmClientFactory(), $logService);
        $this->dispatcher = new MessageDispatcher();
    }

    /**
     * GET /api/v1/chat
     * Lists all chat sessions for the authenticated user, newest first.
     */
    public function index(): void
    {
        $this->requirePermission('chat', 'read');
        $user = $this->getCurrentUser();
        if ($user === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }
        $sessions = $this->chatSessionService->findByUser($user->id);
        $this->success($sessions, ['count' => count($sessions)]);
    }

    /**
     * POST /api/v1/chat/create
     * Creates a new chat session. Requires { agent_id }.
     * An optional { title } may be supplied; if omitted it defaults to null
     * and is populated later by the frontend from the first user message.
     */
    public function create(): void
    {
        $this->requirePermission('chat', 'create');
        $user = $this->getCurrentUser();
        if ($user === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }
        $data = $this->request->getData();

        $agentId = (int)($data['agent_id'] ?? 0);
        if (!$agentId) {
            $this->error('agent_id is required', [], 422);
            return;
        }

        /** @var Agent|null $agent */
        $agent = TableRegistry::getTableLocator()->get('Agents')
            ->find()
            ->where(['Agents.id' => $agentId, 'Agents.is_enabled' => true])
            ->first();

        if ($agent === null) {
            $this->error('Agent not found or not enabled', [], 404);
            return;
        }

        try {
            $session = $this->chatSessionService->create(
                $user->id,
                $agentId,
                $data['title'] ?? null,
            );
            $this->success($session, [], 201);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), [], 422);
        }
    }

    /**
     * GET /api/v1/chat/view/:id
     * Returns a session with its full ordered message history.
     */
    public function view(int $id): void
    {
        $this->requirePermission('chat', 'read');
        $user = $this->getCurrentUser();
        if ($user === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }
        $session = $this->chatSessionService->findById($id);

        if ($session === null || $session->user_id !== $user->id) {
            $this->error('Chat session not found', [], 404);
            return;
        }

        $this->success($session);
    }

    /**
     * DELETE /api/v1/chat/delete/:id
     * Deletes a session and all its messages (cascade).
     */
    public function delete(int $id): void
    {
        $this->requirePermission('chat', 'delete');
        $user = $this->getCurrentUser();
        if ($user === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }
        $session = $this->chatSessionService->findById($id);

        if ($session === null || $session->user_id !== $user->id) {
            $this->error('Chat session not found', [], 404);
            return;
        }

        try {
            $this->chatSessionService->delete($session);
            $this->success(null, [], 204);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), [], 500);
        }
    }

    /**
     * POST /api/v1/chat/message/:id
     *
     * Accepts { message: string } in the request body. Saves the user message,
     * then streams the LLM response back as Server-Sent Events:
     *
     *   data: {"type":"chunk","content":"..."}\n\n   — one per text delta
     *   data: {"type":"done","tokens_used":123}\n\n  — stream complete
     *   data: {"type":"error","message":"..."}\n\n   — on failure
     *
     * This action does NOT use CakePHP's view rendering. Output is emitted
     * directly via echo/flush. The assistant message is persisted to the DB
     * only after the complete stream has been received from the provider.
     */
    public function message(int $id): void
    {
        $this->requirePermission('chat', 'create');
        $user = $this->getCurrentUser();
        if ($user === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }

        $session = $this->chatSessionService->findById($id);
        if ($session === null || $session->user_id !== $user->id) {
            $this->error('Chat session not found', [], 404);
            return;
        }

        $userContent = trim((string)($this->request->getData('message') ?? ''));
        if ($userContent === '') {
            $this->error('message is required', [], 422);
            return;
        }

        /** @var Agent|null $agent */
        $agent = TableRegistry::getTableLocator()->get('Agents')
            ->find()
            ->contain(['AgentContexts'])
            ->where(['Agents.id' => $session->agent_id])
            ->first();

        if ($agent === null) {
            $this->error('Agent not found', [], 404);
            return;
        }

        if (empty($agent->llm_provider)) {
            $this->error(
                "Agent '{$agent->name}' has no LLM provider configured. " .
                'Set llm_provider and llm_model on the agent first.',
                [],
                422,
            );
            return;
        }

        // Persist user message before streaming starts so it is never lost
        $this->chatSessionService->addMessage($session->id, 'user', $userContent);

        // Auto-title the session from the first user message if not yet titled
        if (empty($session->title)) {
            $title = mb_substr($userContent, 0, 80);
            $this->chatSessionService->updateTitle($session, $title);
        }

        // Reload session to get the freshly saved user message in history
        /** @var ChatSession $session */
        $session = $this->chatSessionService->findById($id);

        // --- Switch to raw SSE output, bypass CakePHP view rendering ---
        $this->autoRender = false;
        $sseResponse = $this->response
            ->withHeader('Content-Type', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('X-Accel-Buffering', 'no')
            ->withHeader('Connection', 'keep-alive');
        $this->setResponse($sseResponse);

        // Disable all output buffering layers so chunks reach the browser immediately
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_implicit_flush(true);
        ignore_user_abort(true);

        $executionId = Uuid::uuid4()->toString();
        $assembledContent = '';

        try {
            $history = $this->chatSessionService->buildMessageHistory($session);
            $userId = $user->id;

            // DevOpsOrchestrator agents with OpenAI always run the agentic tool-calling loop.
            // The GitHub token is sourced from the authenticated user's active integration —
            // no need to store it redundantly in agent_contexts.
            $githubToken = $this->loadUserGithubToken($user->id);
            $useAgentLoop = $agent->plugin === 'DevOpsOrchestrator'
                && $agent->llm_provider === 'openai'
                && $githubToken !== null;

            if ($useAgentLoop) {
                $llmResponse = $this->runAgentLoop($agent, $history, $executionId, $githubToken, $assembledContent);
            } else {
                $llmResponse = $this->llmService->stream(
                    $agent,
                    $history,
                    $executionId,
                    $userId,
                    function (string $delta) use (&$assembledContent): void {
                        $assembledContent .= $delta;
                        echo 'data: ' . json_encode(['type' => 'chunk', 'content' => $delta]) . "\n\n";
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    },
                );
            }

            // Persist the completed assistant message
            $this->chatSessionService->addMessage(
                $session->id,
                'assistant',
                $llmResponse->content,
                $llmResponse->tokensUsed,
                $llmResponse->model,
            );

            echo 'data: ' . json_encode(['type' => 'done', 'tokens_used' => $llmResponse->tokensUsed]) . "\n\n";
            flush();
        } catch (\Throwable $e) {
            echo 'data: ' . json_encode(['type' => 'error', 'message' => $e->getMessage()]) . "\n\n";
            flush();
        }
    }

    /**
     * POST /api/v1/chat/escalate/:id
     * Body: { reason?, assigned_user_id?, user_facing_notice? }
     *
     * Moves a session into pending_human (or directly to human if assigned_user_id is supplied).
     * Any logged-in user may escalate any session they own; admins/superusers may escalate any.
     */
    public function escalate(int $id): void
    {
        $this->requirePermission('chat', 'escalate');
        $user = $this->getCurrentUser();
        if ($user === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }
        $session = $this->loadSessionForHandoff($id, $user);
        if ($session === null) {
            return;
        }

        $data = $this->request->getData();
        $assignTo = null;
        if (!empty($data['assigned_user_id'])) {
            /** @var User|null $assignTo */
            $assignTo = TableRegistry::getTableLocator()->get('Users')
                ->find()->where(['Users.id' => (int)$data['assigned_user_id']])->first();
            if ($assignTo === null) {
                $this->error('assigned_user_id not found', [], 422);
                return;
            }
        }

        try {
            $this->dispatcher->escalateToHuman(
                $session,
                $assignTo,
                $data['reason'] ?? null,
                $data['user_facing_notice'] ?? null,
            );
            $this->success($session);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), [], 422);
        }
    }

    /**
     * POST /api/v1/chat/assign/:id
     * Body: { user_id }
     *
     * Picks up a pending_human session. Self-assign is allowed for any
     * authenticated user; assigning to someone else requires chat:assign.
     */
    public function assign(int $id): void
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }
        $session = $this->loadSessionForHandoff($id, $user, ownerOnly: false);
        if ($session === null) {
            return;
        }

        $targetId = (int)($this->request->getData('user_id') ?? $user->id);
        $isSelfAssign = $targetId === $user->id;
        if (!$isSelfAssign) {
            $this->requirePermission('chat', 'assign');
        }

        /** @var User|null $target */
        $target = TableRegistry::getTableLocator()->get('Users')
            ->find()->where(['Users.id' => $targetId])->first();
        if ($target === null) {
            $this->error('user_id not found', [], 422);
            return;
        }

        try {
            $this->dispatcher->assignToHuman($session, $target);
            $this->success($session);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), [], 422);
        }
    }

    /**
     * POST /api/v1/chat/handoff-back/:id
     * Body: { note? }
     *
     * Returns a human-handled session to agent (LLM) handling. Caller must be
     * the current assignee or hold chat:assign.
     */
    public function handoffBack(int $id): void
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }
        $session = $this->loadSessionForHandoff($id, $user, ownerOnly: false);
        if ($session === null) {
            return;
        }
        if ($session->assigned_user_id !== $user->id) {
            $this->requirePermission('chat', 'assign');
        }

        try {
            $this->dispatcher->returnToAgent($session, $this->request->getData('note'));
            $this->success($session);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), [], 422);
        }
    }

    /**
     * POST /api/v1/chat/human-reply/:id
     * Body: { message }
     *
     * Sends an outbound reply on behalf of the assigned human. Persists with
     * sender_user_id stamped, then enqueues SendMessageJob (or no-ops for
     * channel='web', which delivers via the existing SSE flow).
     */
    public function humanReply(int $id): void
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }
        $session = $this->loadSessionForHandoff($id, $user, ownerOnly: false);
        if ($session === null) {
            return;
        }

        $body = trim((string)($this->request->getData('message') ?? ''));
        if ($body === '') {
            $this->error('message is required', [], 422);
            return;
        }

        try {
            $reply = $this->dispatcher->replyAsHuman($session, $user, $body);
            $this->success($reply, [], 201);
        } catch (HandoffStateException $e) {
            $this->error($e->getMessage(), [], 409);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), [], 422);
        }
    }

    /**
     * GET /api/v1/chat/inbox
     *
     * Lists sessions awaiting a human (pending_human) plus those assigned
     * to the caller (human). Used by the frontend's inbox sidebar filter.
     */
    public function inbox(): void
    {
        $this->requirePermission('chat', 'read');
        $user = $this->getCurrentUser();
        if ($user === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }

        $sessions = TableRegistry::getTableLocator()->get('ChatSessions');
        $rows = $sessions->find()
            ->contain(['Agents', 'Users'])
            ->where([
                'OR' => [
                    ['ChatSessions.assignment_state' => ChatSession::STATE_PENDING_HUMAN],
                    [
                        'ChatSessions.assignment_state' => ChatSession::STATE_HUMAN,
                        'ChatSessions.assigned_user_id' => $user->id,
                    ],
                ],
            ])
            ->orderByDesc('ChatSessions.last_inbound_at')
            ->all()
            ->toList();
        $this->success($rows, ['count' => count($rows)]);
    }

    private function loadSessionForHandoff(int $id, User $user, bool $ownerOnly = true): ?ChatSession
    {
        $session = $this->chatSessionService->findById($id);
        if ($session === null) {
            $this->error('Chat session not found', [], 404);
            return null;
        }
        if ($ownerOnly && $session->user_id !== $user->id) {
            $this->error('Chat session not found', [], 404);
            return null;
        }
        return $session;
    }

    /**
     * Loads the GitHub token from the authenticated user's most-recently-created
     * active GitHub integration. Returns null when no active integration exists.
     *
     * This avoids duplicating the token in agent_contexts — the single source of
     * truth for all GitHub credentials is the github_integrations table.
     */
    private function loadUserGithubToken(int $userId): ?string
    {
        /** @var \App\Model\Entity\GithubIntegration|null $integration */
        $integration = TableRegistry::getTableLocator()
            ->get('GithubIntegrations')
            ->find('activeByUser', userId: $userId)
            ->first();

        return $integration?->token ?: null;
    }

    /**
     * Runs the agentic ReAct loop for tool-enabled agents (OpenAI + GitHub token).
     *
     * Builds the full message history with system prompt, constructs the
     * GitHubToolProvider + AgentLoopService, then runs the loop. SSE events
     * (tool_call, tool_result, chunk) are emitted directly to the response
     * stream via the $onEvent callback.
     *
     * @param LlmMessage[] $history
     * @return \App\Integration\Llm\LlmResponse
     */
    private function runAgentLoop(
        Agent $agent,
        array $history,
        string $executionId,
        string $githubToken,
        string &$assembledContent,
    ): \App\Integration\Llm\LlmResponse {
        $apiKey = (string)(Configure::read('Llm.openaiApiKey') ?? env('OPENAI_API_KEY', ''));
        $openAiClient = new OpenAiClient($apiKey);

        $githubApiUrl = (string)(Configure::read('GitHub.apiUrl') ?? env('GITHUB_API_URL', 'https://api.github.com'));
        $githubClient = new GitHubClient($githubToken, $githubApiUrl);

        $toolProvider = new GitHubToolProvider($githubClient);
        $loopService = new AgentLoopService($openAiClient, $toolProvider);

        // Prepend system prompt (same logic as LlmService::buildMessages)
        $messages = $this->buildAgentMessages($agent, $history);
        $options = ['model' => $agent->llm_model ?? 'gpt-4o'];

        return $loopService->run(
            $messages,
            $options,
            function (string $eventJson) use (&$assembledContent): void {
                $event = json_decode($eventJson, true);
                if (is_array($event) && ($event['type'] ?? '') === 'chunk') {
                    $assembledContent .= (string)($event['content'] ?? '');
                }
                echo 'data: ' . $eventJson . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            },
        );
    }

    /**
     * Prepends system prompt and agent context to the conversation history.
     * Mirrors LlmService::buildMessages() so the agent loop gets the same
     * context injection as normal streaming calls.
     *
     * @param LlmMessage[] $history
     * @return LlmMessage[]
     */
    private function buildAgentMessages(Agent $agent, array $history): array
    {
        $systemParts = [];
        if (!empty($agent->instructions)) {
            $systemParts[] = (string)$agent->instructions;
        }
        if (!empty($agent->agent_contexts)) {
            $contextLines = [];
            foreach ($agent->agent_contexts as $ctx) {
                // Never leak the github_token into the system prompt
                if ($ctx->key === 'github_token') {
                    continue;
                }
                $contextLines[] = "{$ctx->key}: {$ctx->value}";
            }
            if (!empty($contextLines)) {
                $systemParts[] = "Agent context:\n" . implode("\n", $contextLines);
            }
        }

        $messages = [];
        if (!empty($systemParts)) {
            $messages[] = new LlmMessage('system', implode("\n\n", $systemParts));
        }
        return array_merge($messages, $history);
    }
}
