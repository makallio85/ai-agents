<?php
declare(strict_types=1);

namespace App\Messaging\Service;

use App\Messaging\Contract\MessageHandlerInterface;
use App\Service\AgentLogService;
use App\Service\ChatSessionService;
use App\Service\LlmService;
use App\Model\Entity\Agent;
use App\Model\Entity\ChatMessage;
use App\Model\Entity\ChatSession;
use Cake\ORM\TableRegistry;
use Ramsey\Uuid\Uuid;

/**
 * Default reply behaviour: assemble the session's history, ask the agent's
 * configured LLM for a completion, persist the assistant message, and let
 * MessageDispatcher route it through the channel transport.
 *
 * Used non-streaming because WhatsApp / email and other batched channels
 * don't have an SSE pipe to the user. The browser chat UI continues to use
 * LlmService::stream() directly via ChatController; this handler covers
 * everything else.
 *
 * Plugins that need command logic on top of the LLM (e.g. DevOpsOrchestrator
 * watching for "/done") wrap this handler instead of replacing it.
 */
class LlmHandler implements MessageHandlerInterface
{
    public function __construct(
        private readonly LlmService $llmService,
        private readonly ChatSessionService $chatSessionService,
        private readonly MessageDispatcher $dispatcher,
        private readonly AgentLogService $logService,
    ) {
    }

    public function handleMessage(Agent $agent, ChatSession $session, ChatMessage $inbound): void
    {
        // Reload the session with eager-loaded messages so buildMessageHistory has the full context.
        $fullSession = $this->chatSessionService->findById($session->id);
        if ($fullSession === null) {
            throw new \RuntimeException("Session {$session->id} disappeared between persist and dispatch");
        }

        // Make sure the agent passed in carries its agent_contexts; the inbound job hands us
        // an entity loaded with associations, but we re-fetch defensively for cases where it
        // was constructed elsewhere.
        if (!isset($agent->agent_contexts)) {
            /** @var Agent $agent */
            $agent = TableRegistry::getTableLocator()->get('Agents')
                ->find()
                ->contain(['AgentContexts'])
                ->where(['Agents.id' => $agent->id])
                ->firstOrFail();
        }

        $history = $this->chatSessionService->buildMessageHistory($fullSession);
        $executionId = Uuid::uuid4()->toString();

        $response = $this->llmService->complete($agent, $history, $executionId, $session->user_id);

        // Persist token / model attribution against the outbound row that the dispatcher creates.
        // Pass $inbound so the outbound metadata carries inbound_thread_id for correct Slack threading.
        $outbound = $this->dispatcher->reply($fullSession, $response->content, $inbound);
        if ($response->tokensUsed !== null || $response->model !== null) {
            $outbound->tokens_used = $response->tokensUsed;
            $outbound->model_used = $response->model;
            TableRegistry::getTableLocator()->get('ChatMessages')->save($outbound);
        }

        $this->logService->info(
            $agent->id,
            $executionId,
            'LlmHandler reply dispatched',
            [
                'session_id' => $session->id,
                'channel' => $session->channel,
                'inbound_message_id' => $inbound->id,
                'outbound_message_id' => $outbound->id,
            ],
            $session->user_id,
        );
    }
}
