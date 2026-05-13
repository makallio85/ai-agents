<?php
declare(strict_types=1);

namespace App\Messaging\Service;

use App\Messaging\Dto\OutboundMessage;
use App\Messaging\Exception\HandoffStateException;
use App\Messaging\Job\SendMessageJob;
use App\Model\Entity\ChatMessage;
use App\Model\Entity\ChatSession;
use App\Model\Entity\User;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\Queue\QueueManager;
use RuntimeException;

/**
 * The single outbound API for both agent handlers and human operators.
 *
 * All replies — whether produced by an LLM, by a plugin's command logic,
 * or by a human typing in the UI — flow through one of the methods on this
 * class. Channel-specific concerns (24h windows, templates, threading) are
 * the transport's responsibility, not the caller's.
 *
 * Handoff orchestration lives here too: escalateToHuman / assignToHuman /
 * returnToAgent flip the chat_sessions.assignment_state column. The inbound
 * job consults that state to decide whether to invoke an agent handler or
 * leave the inbound parked for a human reply.
 */
class MessageDispatcher
{
    public function __construct(
        private readonly ?string $queueName = null,
    ) {
    }

    /**
     * Agent reply path. Persists an outbound assistant message and queues delivery.
     *
     * $triggeringInbound should be the ChatMessage that caused this reply to be
     * generated. When supplied and the inbound has an external_thread_id, that
     * value is stored in the outbound message's metadata under 'inbound_thread_id'
     * so that asynchronous channel transports (e.g. SlackTransport) can use it
     * at send time without re-querying the DB. This prevents the race condition
     * where a second inbound arrives between reply creation and delivery, causing
     * the transport to pick up the wrong thread_ts and post the reply in the
     * wrong thread.
     */
    public function reply(
        ChatSession $session,
        string|OutboundMessage $message,
        ?ChatMessage $triggeringInbound = null,
    ): ChatMessage {
        $payload = is_string($message) ? OutboundMessage::text($message) : $message;

        // Attach the triggering inbound's thread id to the outbound metadata so
        // channel transports can route to the correct thread asynchronously.
        if (
            $triggeringInbound !== null
            && !empty($triggeringInbound->external_thread_id)
        ) {
            $merged = array_merge(
                $payload->metadata,
                ['inbound_thread_id' => $triggeringInbound->external_thread_id],
            );
            $payload = new OutboundMessage($payload->body, $payload->contentType, $merged);
        }

        return $this->persistAndEnqueue(
            $session,
            $payload,
            ChatMessage::ROLE_ASSISTANT,
            senderUserId: null,
            proactive: false,
        );
    }

    /**
     * Human handoff outbound path. Persists with sender_user_id so the audit
     * trail records who replied, and enqueues delivery through the same
     * transport pipeline as an agent reply.
     *
     * @throws HandoffStateException If the session is not in a human-handled state,
     *                               or the supplied user is not the assignee.
     */
    public function replyAsHuman(ChatSession $session, User $human, string|OutboundMessage $message): ChatMessage
    {
        if (!$session->isHumanHandled() && !$session->isPendingHuman()) {
            throw new HandoffStateException(
                "Session {$session->id} is not in a human-handled state (state={$session->assignment_state})"
            );
        }
        if ($session->assigned_user_id !== null && $session->assigned_user_id !== $human->id) {
            throw new HandoffStateException(
                "User {$human->id} is not the assignee for session {$session->id}"
            );
        }

        $payload = is_string($message) ? OutboundMessage::text($message) : $message;
        return $this->persistAndEnqueue(
            $session,
            $payload,
            ChatMessage::ROLE_ASSISTANT,
            senderUserId: $human->id,
            proactive: false,
        );
    }

    /**
     * Proactive (template-driven for WhatsApp) outbound path. Bypasses the
     * 24h window check; the transport selects the template path for channels
     * that have one, or treats it the same as send() for channels that don't.
     */
    public function proactive(ChatSession $session, OutboundMessage $message): ChatMessage
    {
        return $this->persistAndEnqueue(
            $session,
            $message,
            ChatMessage::ROLE_ASSISTANT,
            senderUserId: null,
            proactive: true,
        );
    }

    /**
     * Bridge-internal system message (OTP prompts, "a human will reply shortly", etc.).
     * Persisted with role='system' so it shows up in history but is not
     * fed to the LLM as part of an LLM-only conversation history.
     */
    public function sendSystem(ChatSession $session, string $body): ChatMessage
    {
        $payload = OutboundMessage::text($body);
        return $this->persistAndEnqueue(
            $session,
            $payload,
            ChatMessage::ROLE_SYSTEM,
            senderUserId: null,
            proactive: false,
        );
    }

    /**
     * Move the session into pending_human (or directly human if $assignTo is given).
     * Optionally informs the user via a system message.
     */
    public function escalateToHuman(
        ChatSession $session,
        ?User $assignTo = null,
        ?string $reason = null,
        ?string $userFacingNotice = null,
    ): void {
        $sessions = TableRegistry::getTableLocator()->get('ChatSessions');
        $session->assignment_state = $assignTo === null
            ? ChatSession::STATE_PENDING_HUMAN
            : ChatSession::STATE_HUMAN;
        $session->assigned_user_id = $assignTo?->id;
        $session->assigned_at = $assignTo === null ? null : new DateTime();
        $session->escalation_reason = $reason;

        if (!$sessions->save($session)) {
            throw new RuntimeException(
                "Failed to escalate session {$session->id}: " . json_encode($session->getErrors())
            );
        }

        if ($userFacingNotice !== null) {
            $this->sendSystem($session, $userFacingNotice);
        }
    }

    /**
     * Move pending_human -> human once a specific user picks the session up.
     */
    public function assignToHuman(ChatSession $session, User $human): void
    {
        $sessions = TableRegistry::getTableLocator()->get('ChatSessions');
        $session->assignment_state = ChatSession::STATE_HUMAN;
        $session->assigned_user_id = $human->id;
        $session->assigned_at = new DateTime();
        if (!$sessions->save($session)) {
            throw new RuntimeException(
                "Failed to assign session {$session->id}: " . json_encode($session->getErrors())
            );
        }
    }

    /**
     * Return a session to agent handling. Next inbound from the user routes
     * back through MessageHandlerRegistry → LlmHandler.
     */
    public function returnToAgent(ChatSession $session, ?string $note = null): void
    {
        $sessions = TableRegistry::getTableLocator()->get('ChatSessions');
        $session->assignment_state = ChatSession::STATE_AGENT;
        $session->assigned_user_id = null;
        $session->assigned_at = null;
        $session->escalation_reason = $note;
        if (!$sessions->save($session)) {
            throw new RuntimeException(
                "Failed to return session {$session->id} to agent: " . json_encode($session->getErrors())
            );
        }
    }

    /**
     * Decides whether the user wants this reply as text or audio.
     *
     * Reads users.preferred_reply_mode:
     *   - 'audio' : reply as audio when the channel supports outbound audio,
     *               otherwise fall back to text
     *   - 'text'  : always reply as text
     *   - 'auto'  : mirror the user's most recent inbound — if they sent
     *               audio, reply audio (when supported); otherwise text
     *
     * System messages (role='system') stay as text regardless because they
     * are platform notices (OTP prompts, error apologies) where TTS
     * latency would slow critical UX paths.
     */
    public function shouldReplyAsAudio(ChatSession $session, string $role): bool
    {
        if ($role === ChatMessage::ROLE_SYSTEM) {
            return false;
        }
        $sessions = TableRegistry::getTableLocator()->get('ChatSessions');
        /** @var ChatSession|null $loaded */
        $loaded = $sessions->find()
            ->contain(['Users'])
            ->where(['ChatSessions.id' => $session->id])
            ->first();
        $preferred = $loaded?->user?->preferred_reply_mode ?? 'auto';

        if ($preferred === 'text') {
            return false;
        }
        if ($preferred === 'audio') {
            return true;
        }
        // 'auto' — mirror the latest inbound's content_type.
        $messages = TableRegistry::getTableLocator()->get('ChatMessages');
        $latest = $messages->find()
            ->where([
                'chat_session_id' => $session->id,
                'direction' => ChatMessage::DIRECTION_INBOUND,
            ])
            ->orderByDesc('created')
            ->first();
        return ($latest?->content_type ?? null) === ChatMessage::CONTENT_AUDIO;
    }

    /**
     * Persists a chat_messages row in queued state and enqueues SendMessageJob
     * for non-web channels. For 'web' (the existing SSE flow) the row is
     * persisted and we leave delivery to the existing controller.
     */
    private function persistAndEnqueue(
        ChatSession $session,
        OutboundMessage $message,
        string $role,
        ?int $senderUserId,
        bool $proactive,
    ): ChatMessage {
        $messages = TableRegistry::getTableLocator()->get('ChatMessages');
        // Audio routing is decided at the dispatch layer, not the send-job
        // layer, so the chat_messages row is correctly tagged from the
        // start (and the chat-history UI shows the audio bubble even
        // before the send-job runs).
        $contentType = $message->contentType;
        $synthesiseAudio = false;
        if ($contentType === OutboundMessage::CONTENT_TEXT && $this->shouldReplyAsAudio($session, $role)) {
            $contentType = ChatMessage::CONTENT_AUDIO;
            $synthesiseAudio = true;
        }

        $entity = $messages->newEntity([
            'chat_session_id' => $session->id,
            'role' => $role,
            'channel' => $session->channel ?? ChatSession::CHANNEL_WEB,
            'direction' => ChatMessage::DIRECTION_OUTBOUND,
            'sender_user_id' => $senderUserId,
            'content' => $message->body,
            'content_type' => $contentType,
            'status' => ChatMessage::STATUS_QUEUED,
            'metadata' => $message->metadata !== [] ? json_encode($message->metadata) : null,
        ]);

        if (!$messages->save($entity)) {
            throw new RuntimeException(
                'Failed to persist outbound message: ' . json_encode($entity->getErrors())
            );
        }

        /** @var ChatMessage $entity */

        // Web channel delivers via SSE inside ChatController, not via the queue.
        if (($session->channel ?? ChatSession::CHANNEL_WEB) === ChatSession::CHANNEL_WEB) {
            $entity->status = ChatMessage::STATUS_SENT;
            $entity->sent_at = new DateTime();
            $messages->save($entity);
            return $entity;
        }

        QueueManager::push(SendMessageJob::class, [
            'message_id' => $entity->id,
            'proactive' => $proactive,
            'synthesise_audio' => $synthesiseAudio,
        ], ['queue' => $this->queueName ?? 'default']);

        return $entity;
    }
}
