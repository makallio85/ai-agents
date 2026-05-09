<?php
declare(strict_types=1);

namespace App\Messaging\Job;

use App\Messaging\Dto\InboundEnvelope;
use App\Messaging\Service\ChannelRegistry;
use App\Messaging\Service\MessageDispatcher;
use App\Messaging\Service\MessageHandlerRegistry;
use App\Model\Entity\Agent;
use App\Model\Entity\ChatMessage;
use App\Model\Entity\ChatSession;
use App\Model\Entity\InboundEvent;
use App\Model\Entity\User;
use App\Service\AgentLogService;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

/**
 * Channel-agnostic inbound processor.
 *
 * Loads the InboundEvent persisted by a webhook controller, asks the
 * registered transport to parseInbound(), then for each envelope:
 *   - resolves agent + user (via transport)
 *   - drives OTP verification when the sender is unknown (transport-owned)
 *   - finds-or-creates the ChatSession
 *   - de-duplicates by (channel, external_message_id, direction)
 *   - persists the inbound ChatMessage
 *   - branches on session.assignment_state:
 *       agent  -> resolves handler from MessageHandlerRegistry and runs it
 *       human  -> skips handler (a human will reply via /api/v1/chat/human-reply)
 */
class ProcessInboundMessageJob implements JobInterface
{
    public static int $maxAttempts = 3;
    public static bool $shouldBeUnique = false;

    public function __construct(
        private readonly ChannelRegistry $channels,
        private readonly MessageHandlerRegistry $handlers,
        private readonly MessageDispatcher $dispatcher,
        private readonly AgentLogService $logService,
    ) {
    }

    public function execute(Message $message): ?string
    {
        $inboundEventId = (int)$message->getArgument('inbound_event_id', 0);
        if ($inboundEventId === 0) {
            return Processor::REJECT;
        }

        $events = TableRegistry::getTableLocator()->get('InboundEvents');
        /** @var InboundEvent|null $event */
        $event = $events->find()->where(['InboundEvents.id' => $inboundEventId])->first();
        if ($event === null) {
            return Processor::REJECT;
        }
        if ($event->processed_at !== null) {
            return Processor::ACK;
        }
        if (!$this->channels->has($event->channel)) {
            $event->error_message = "No transport registered for channel '{$event->channel}'";
            $events->save($event);
            return Processor::REJECT;
        }

        $transport = $this->channels->get($event->channel);

        try {
            $envelopes = $transport->parseInbound($event);
        } catch (\Throwable $e) {
            $event->error_message = 'parseInbound failed: ' . $e->getMessage();
            $events->save($event);
            return Processor::REJECT;
        }

        foreach ($envelopes as $envelope) {
            if ($envelope->kind === InboundEnvelope::KIND_STATUS) {
                $this->applyStatusUpdate($envelope);
                continue;
            }
            $this->processMessage($envelope);
        }

        $event->processed_at = new DateTime();
        $events->save($event);
        return Processor::ACK;
    }

    private function processMessage(InboundEnvelope $envelope): void
    {
        $transport = $this->channels->get($envelope->channel);

        $agent = $transport->resolveAgentByExternalAccount($envelope->externalAccountId);
        if ($agent === null) {
            // No agent claims this account id — silently skip (event row keeps the audit).
            return;
        }

        $user = $transport->resolveUserByExternalIdentifier($envelope->externalIdentifier);

        if ($user === null) {
            if (!$transport->requiresVerification()) {
                return; // unknown sender on a non-verifying channel: nothing to attach to
            }
            $verified = $transport->handleUnverifiedSender($envelope, $agent);
            if ($verified === null) {
                return; // OTP issued or rejected; await next inbound
            }
            $user = $verified;
            // Transport's handleUnverifiedSender is responsible for replaying any
            // buffered original message via the dispatcher / direct persistence,
            // so the current inbound (which was the code submission) does not get
            // turned into an agent-visible ChatMessage. If the transport instead
            // returned a User on first contact (no verification needed), fall
            // through and persist the current inbound normally.
            return;
        }

        $session = $this->findOrCreateSession($user, $agent, $envelope);
        $inbound = $this->persistInbound($envelope, $session);
        if ($inbound === null) {
            return; // duplicate by external_message_id
        }

        // Update the session's last_inbound_at so transports can enforce 24h windows.
        $session->last_inbound_at = new DateTime();
        TableRegistry::getTableLocator()->get('ChatSessions')->save($session);

        // Approval gate: WhatsApp guests created via OTP onboarding land in
        // approval_state='pending'. Their messages are stored (so a superuser
        // can review the conversation when approving) but no agent handler
        // runs and we send a one-time courtesy notice on the first inbound.
        if (!$this->isApproved($user)) {
            $this->notifyPendingApproval($agent, $session, $user, $inbound);
            return;
        }

        $this->dispatchToHandlerOrHuman($agent, $session, $inbound, $user);
    }

    private function isApproved(User $user): bool
    {
        if (isset($user->is_approved)) {
            return (bool)$user->is_approved;
        }
        // Default true for legacy users that pre-date the approval column.
        return true;
    }

    private function notifyPendingApproval(Agent $agent, ChatSession $session, User $user, ChatMessage $inbound): void
    {
        $this->logService->info(
            $agent->id,
            'inbound-' . $inbound->id,
            'Inbound from unapproved user; awaiting superuser approval',
            ['session_id' => $session->id, 'user_id' => $user->id, 'phone' => $user->phone_number ?? null],
            $user->id,
        );

        // Only send the courtesy notice once per session — repeated reminders
        // would just spam the user (and burn the 24h window on follow-ups).
        $messages = TableRegistry::getTableLocator()->get('ChatMessages');
        $alreadyNotified = $messages->find()
            ->where([
                'chat_session_id' => $session->id,
                'role' => ChatMessage::ROLE_SYSTEM,
                'metadata LIKE' => '%pending_approval_notice%',
            ])->count() > 0;
        if ($alreadyNotified) {
            return;
        }
        $notice = $this->dispatcher->sendSystem(
            $session,
            "Thanks for messaging — your access is pending approval. We'll let you know once it's been reviewed."
        );
        // Tag the notice so the duplicate-suppression check above can find it.
        $notice->metadata = json_encode(['pending_approval_notice' => true]);
        $messages->save($notice);
    }

    private function findOrCreateSession(User $user, Agent $agent, InboundEnvelope $envelope): ChatSession
    {
        return TableRegistry::getTableLocator()->get('ChatSessions')
            ->findOrCreateForChannel(
                $user->id,
                $agent->id,
                $envelope->channel,
                $envelope->externalIdentifier,
            );
    }

    private function persistInbound(InboundEnvelope $envelope, ChatSession $session): ?ChatMessage
    {
        $messages = TableRegistry::getTableLocator()->get('ChatMessages');
        $existing = $messages->find()->where([
            'ChatMessages.channel' => $envelope->channel,
            'ChatMessages.external_message_id' => $envelope->externalMessageId,
            'ChatMessages.direction' => ChatMessage::DIRECTION_INBOUND,
        ])->first();
        if ($existing !== null) {
            return null;
        }

        $entity = $messages->newEntity([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_USER,
            'channel' => $envelope->channel,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => $envelope->body,
            'content_type' => $envelope->contentType,
            'media_url' => $envelope->mediaUrl,
            'media_mime_type' => $envelope->mediaMimeType,
            'external_message_id' => $envelope->externalMessageId,
            'external_thread_id' => $envelope->externalThreadId,
            'status' => ChatMessage::STATUS_RECEIVED,
            'metadata' => $envelope->rawPayload !== [] ? json_encode($envelope->rawPayload) : null,
        ]);

        if (!$messages->save($entity)) {
            $this->logService->error(
                $session->agent_id,
                'inbound-' . uniqid('', true),
                'Failed to persist inbound chat_messages row',
                json_encode($entity->getErrors()) ?: '',
                ['envelope' => $envelope->externalMessageId],
            );
            return null;
        }
        /** @var ChatMessage $entity */
        return $entity;
    }

    private function dispatchToHandlerOrHuman(Agent $agent, ChatSession $session, ChatMessage $inbound, User $user): void
    {
        if ($session->isHumanHandled() || $session->isPendingHuman()) {
            $this->logService->info(
                $agent->id,
                'inbound-' . $inbound->id,
                'Inbound on human-handled session; awaiting human reply',
                [
                    'session_id' => $session->id,
                    'assignment_state' => $session->assignment_state,
                    'assigned_user_id' => $session->assigned_user_id,
                ],
                $user->id,
            );
            return;
        }

        $handler = $this->handlers->resolve($agent->plugin ?? null);
        try {
            $handler->handleMessage($agent, $session, $inbound);
        } catch (\Throwable $e) {
            $this->logService->error(
                $agent->id,
                'inbound-' . $inbound->id,
                'MessageHandler threw',
                $e->getMessage(),
                ['session_id' => $session->id, 'plugin' => $agent->plugin],
                $user->id,
            );
            $this->dispatcher->sendSystem(
                $session,
                "Sorry — something went wrong handling your message. We'll look into it.",
            );
        }
    }

    private function applyStatusUpdate(InboundEnvelope $envelope): void
    {
        if ($envelope->statusUpdate === null) {
            return;
        }
        $messages = TableRegistry::getTableLocator()->get('ChatMessages');
        $row = $messages->find()->where([
            'ChatMessages.channel' => $envelope->channel,
            'ChatMessages.external_message_id' => $envelope->externalMessageId,
            'ChatMessages.direction' => ChatMessage::DIRECTION_OUTBOUND,
        ])->first();
        if ($row === null) {
            return;
        }
        $row->status = $envelope->statusUpdate;
        $now = new DateTime();
        if ($envelope->statusUpdate === ChatMessage::STATUS_DELIVERED) {
            $row->delivered_at = $now;
        } elseif ($envelope->statusUpdate === ChatMessage::STATUS_READ) {
            $row->read_at = $now;
        }
        $messages->save($row);
    }
}
