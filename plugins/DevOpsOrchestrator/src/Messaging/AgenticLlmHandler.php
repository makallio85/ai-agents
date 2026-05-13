<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Messaging;

use App\Integration\Llm\LlmMessage;
use App\Integration\Llm\OpenAiClient;
use App\Messaging\Contract\MessageHandlerInterface;
use App\Messaging\Service\LlmHandler;
use App\Messaging\Service\MessageDispatcher;
use App\Model\Entity\Agent;
use App\Model\Entity\ChatMessage;
use App\Model\Entity\ChatSession;
use App\Service\AgentTools\AgentLoopService;
use App\Service\AgentTools\GitHubToolProvider;
use App\Service\ChatSessionService;
use Cake\Core\Configure;
use Cake\Log\LogTrait;
use Cake\ORM\TableRegistry;
use DevOpsOrchestrator\Integration\GitHub\GitHubClient;
use Ramsey\Uuid\Uuid;

/**
 * Agentic message handler for DevOpsOrchestrator agents on non-SSE channels (Slack, WhatsApp, etc.).
 *
 * Replaces IssueIntakeHandler to enable fully conversational interaction with GitHub:
 * users can ask about issues, commits, files, and pull requests in natural language
 * rather than using slash commands.
 *
 * The handler runs AgentLoopService (the same ReAct loop used by ChatController for
 * the web UI) in non-streaming mode. Instead of emitting SSE events to a browser,
 * the $onEvent callback is a no-op accumulator, and the final LLM response is sent
 * via MessageDispatcher::reply() through the appropriate channel transport.
 *
 * Write operations (create_or_update_file, create_pull_request) are intentionally
 * absent from GitHubToolProvider — this bot is scoped to read + issue management only.
 *
 * Falls back to the plain LlmHandler when:
 *   - The agent's llm_provider is not 'openai'
 *   - No GitHub integration (token) is available for any administrator
 *   - The OpenAI API key is not configured
 *
 * GitHub token source: the first active integration belonging to any user with the
 * 'administrator' role. This is the admin who configured the GitHub integration in
 * the platform's Settings → GitHub section.
 */
class AgenticLlmHandler implements MessageHandlerInterface
{
    use LogTrait;

    public function __construct(
        private readonly LlmHandler $llmHandler,
        private readonly MessageDispatcher $dispatcher,
        private readonly ChatSessionService $chatSessionService,
    ) {
    }

    public function handleMessage(Agent $agent, ChatSession $session, ChatMessage $inbound): void
    {
        if ($agent->llm_provider !== 'openai') {
            $this->llmHandler->handleMessage($agent, $session, $inbound);
            return;
        }

        $apiKey = (string)(Configure::read('Llm.openaiApiKey') ?? env('OPENAI_API_KEY', ''));
        if ($apiKey === '') {
            $this->llmHandler->handleMessage($agent, $session, $inbound);
            return;
        }

        $githubToken = $this->loadAdminGithubToken();
        if ($githubToken === null) {
            $this->llmHandler->handleMessage($agent, $session, $inbound);
            return;
        }

        $this->runAgentLoop($agent, $session, $inbound, $apiKey, $githubToken);
    }

    /**
     * Runs the agentic ReAct loop and dispatches the final answer via MessageDispatcher.
     *
     * The $onEvent callback collects only the final 'chunk' event's content; all tool
     * progress events are swallowed (there is no SSE pipe for non-web channels).
     */
    private function runAgentLoop(
        Agent $agent,
        ChatSession $session,
        ChatMessage $inbound,
        string $apiKey,
        string $githubToken,
    ): void {
        $executionId = Uuid::uuid4()->toString();

        $fullSession = $this->chatSessionService->findById($session->id);
        if ($fullSession === null) {
            throw new \RuntimeException("Session {$session->id} disappeared before agent loop could start");
        }

        if (!isset($agent->agent_contexts)) {
            /** @var Agent $agent */
            $agent = TableRegistry::getTableLocator()->get('Agents')
                ->find()
                ->contain(['AgentContexts'])
                ->where(['Agents.id' => $agent->id])
                ->firstOrFail();
        }

        $history = $this->chatSessionService->buildMessageHistory($fullSession);
        $messages = $this->buildAgentMessages($agent, $history);
        $options = ['model' => $agent->llm_model ?? 'gpt-4o'];

        $githubApiUrl = (string)(Configure::read('GitHub.apiUrl') ?? env('GITHUB_API_URL', 'https://api.github.com'));
        $githubClient = new GitHubClient($githubToken, $githubApiUrl);
        $toolProvider = new GitHubToolProvider($githubClient);
        $loopService = new AgentLoopService(new OpenAiClient($apiKey), $toolProvider);

        try {
            $llmResponse = $loopService->run(
                $messages,
                $options,
                function (string $_eventJson): void {
                    // No-op: SSE events are only meaningful for the browser chat UI.
                    // For Slack and other channel transports the final assembled
                    // response is what gets dispatched, not the intermediate chunks.
                },
            );
        } catch (\Throwable $e) {
            $this->log(
                "AgenticLlmHandler loop failed for session {$session->id}: {$e->getMessage()}",
                'error',
                ['scope' => 'agentic_llm_handler', 'execution_id' => $executionId],
            );
            $this->dispatcher->reply($session, "I encountered an error while processing your request. Please try again.");
            return;
        }

        $outbound = $this->dispatcher->reply($fullSession, $llmResponse->content);

        $outbound->tokens_used = $llmResponse->tokensUsed;
        $outbound->model_used = $llmResponse->model;
        TableRegistry::getTableLocator()->get('ChatMessages')->save($outbound);

        $this->log(
            'AgenticLlmHandler reply dispatched',
            'info',
            [
                'scope' => 'agentic_llm_handler',
                'execution_id' => $executionId,
                'session_id' => $session->id,
                'channel' => $session->channel,
                'inbound_message_id' => $inbound->id,
                'outbound_message_id' => $outbound->id,
                'tokens_used' => $llmResponse->tokensUsed,
            ],
        );
    }

    /**
     * Prepends system prompt and agent context to the conversation history.
     *
     * Mirrors ChatController::buildAgentMessages() so the agentic loop receives
     * the same context injection as the web UI — system instructions, agent
     * context values (minus secrets), and tool error handling guidance.
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
                if ($ctx->context_key === 'github_token') {
                    continue;
                }
                $contextLines[] = "{$ctx->context_key}: {$ctx->value}";
            }
            if (!empty($contextLines)) {
                $systemParts[] = "Agent context:\n" . implode("\n", $contextLines);
            }
        }

        $systemParts[] = implode("\n", [
            'Tool error handling:',
            '- When a tool returns a result starting with TOOL_ERROR, report the exact error to the user.',
            '- Never guess or invent a cause. Use only what the error message states.',
            '- A 403 error means a permissions or token scope problem — NOT a rate limit.',
            '- A 429 error means rate limit exceeded.',
            '- A 404 error means the resource (repo, issue, file) was not found.',
            '- Do not retry a failed tool call unless the user explicitly asks you to.',
        ]);

        $messages = [new LlmMessage('system', implode("\n\n", $systemParts))];
        return array_merge($messages, $history);
    }

    /**
     * Loads a GitHub personal access token from the first active integration
     * belonging to any user with the 'administrator' role.
     *
     * This is the admin who set up the GitHub integration in the platform's
     * Settings page. The token is not stored per-agent; the admin's integration
     * is the single source of truth for all DevOpsOrchestrator GitHub access.
     */
    private function loadAdminGithubToken(): ?string
    {
        /** @var \App\Model\Entity\GithubIntegration|null $integration */
        $integration = TableRegistry::getTableLocator()
            ->get('GithubIntegrations')
            ->find()
            ->innerJoinWith('Users.Roles', function ($q) {
                return $q->where(['Roles.slug' => 'administrator']);
            })
            ->where(['GithubIntegrations.is_active' => true])
            ->orderByDesc('GithubIntegrations.created')
            ->first();

        return $integration?->token ?: null;
    }
}
