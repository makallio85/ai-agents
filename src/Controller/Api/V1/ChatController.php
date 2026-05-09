<?php
declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Integration\Llm\LlmClientFactory;
use App\Model\Entity\Agent;
use App\Model\Entity\ChatSession;
use App\Service\AgentLogService;
use App\Service\ChatSessionService;
use App\Service\LlmService;
use Cake\ORM\TableRegistry;
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

    public function initialize(): void
    {
        parent::initialize();
        $logService = new AgentLogService();
        $this->chatSessionService = new ChatSessionService();
        $this->llmService = new LlmService(new LlmClientFactory(), $logService);
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
}
