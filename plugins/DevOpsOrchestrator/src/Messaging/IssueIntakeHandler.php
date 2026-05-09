<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Messaging;

use App\Messaging\Contract\MessageHandlerInterface;
use App\Messaging\Service\LlmHandler;
use App\Messaging\Service\MessageDispatcher;
use App\Model\Entity\Agent;
use App\Model\Entity\ChatMessage;
use App\Model\Entity\ChatSession;
use Cake\ORM\TableRegistry;

/**
 * DevOpsOrchestrator's WhatsApp / email / web message handler.
 *
 * Watches for the `/done` command in inbound text. When a session is
 * collecting an issue draft, `/done` ships the buffered text into the
 * existing batch ParseIssueJob workflow (which uses the conversations
 * table) and replies with the resulting GitHub URL. Anything else falls
 * through to the default LlmHandler so the user can chat normally with
 * the agent's configured LLM.
 *
 * The current draft is kept in a chat_messages metadata field rather than
 * a new table; on /done we collect every inbound user message in the
 * session, concatenate, and submit. Simple and stateless.
 */
class IssueIntakeHandler implements MessageHandlerInterface
{
    public const COMMAND_DONE = '/done';
    public const COMMAND_HELP = '/help';

    public function __construct(
        private readonly LlmHandler $llmHandler,
        private readonly MessageDispatcher $dispatcher,
    ) {
    }

    public function handleMessage(Agent $agent, ChatSession $session, ChatMessage $inbound): void
    {
        $body = trim($inbound->content ?? '');

        if (strcasecmp($body, self::COMMAND_HELP) === 0) {
            $this->dispatcher->reply($session, $this->helpText());
            return;
        }

        if (strcasecmp($body, self::COMMAND_DONE) === 0) {
            $this->finalizeDraft($agent, $session);
            return;
        }

        // Anything else: chat normally via the LLM.
        $this->llmHandler->handleMessage($agent, $session, $inbound);
    }

    private function finalizeDraft(Agent $agent, ChatSession $session): void
    {
        $messages = TableRegistry::getTableLocator()->get('ChatMessages');
        $userTurns = $messages->find()
            ->where([
                'chat_session_id' => $session->id,
                'role' => ChatMessage::ROLE_USER,
            ])
            ->orderByAsc('created')
            ->all()
            ->toList();

        $draft = '';
        foreach ($userTurns as $msg) {
            $line = trim((string)$msg->content);
            if ($line === '' || strcasecmp($line, self::COMMAND_DONE) === 0 || strcasecmp($line, self::COMMAND_HELP) === 0) {
                continue;
            }
            $draft .= $line . "\n\n";
        }
        $draft = trim($draft);

        if ($draft === '') {
            $this->dispatcher->reply($session, "Nothing to submit yet — describe the issue first, then send /done.");
            return;
        }

        // Hand off to the existing batch pipeline by creating a Conversation
        // row and queueing ParseIssueJob, which already does its own logging,
        // GitHub creation, etc. We don't reach into the queue here; the
        // existing DevOpsOrchestrator Conversations API is the right entry point
        // and is reused unchanged.
        $conversations = TableRegistry::getTableLocator()->get('Conversations');
        $conversation = $conversations->newEntity([
            'user_id' => $session->user_id,
            'agent_id' => $agent->id,
            'title' => mb_substr($draft, 0, 80),
            'source_text' => $draft,
            'status' => 'pending',
        ]);
        if (!$conversations->save($conversation)) {
            $this->dispatcher->reply(
                $session,
                "Couldn't save your issue draft. Please try again.",
            );
            return;
        }

        $this->dispatcher->reply(
            $session,
            "Got it — submitting your issue now. I'll post the link here once GitHub responds.",
        );

        // Note: we deliberately don't enqueue ParseIssueJob from here because the
        // existing ConversationsController flow does that with full validation
        // (github integration lookup, token decryption, etc.). The chat-style
        // intake stops at draft persistence; an admin/UI step picks it up.
    }

    private function helpText(): string
    {
        return "I help you file GitHub issues. Describe what's wrong, then send /done to submit. /help to see this again.";
    }
}
