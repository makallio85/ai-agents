<?php
declare(strict_types=1);

namespace App\Messaging\Job;

use App\Messaging\Dto\InboundEnvelope;
use App\Messaging\Service\ChannelRegistry;
use App\Messaging\Service\InboundDispatchService;
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
use Cake\Queue\QueueManager;
use Interop\Queue\Processor;

/**
 * Channel-agnostic inbound processor.
 *
 * Loads the InboundEvent persisted by a webhook controller, asks the
 * registered transport to parseInbound(), then for each envelope:
 *   - resolves agent + user (via transport)
 *   - drives identity onboarding when the sender is unknown
 *   - finds-or-creates the ChatSession
 *   - de-duplicates by (channel, external_message_id, direction)
 *   - persists the inbound ChatMessage
 *   - if the message is audio, enqueues TranscribeAudioJob (it will run
 *     the handler dispatch after transcription completes)
 *   - otherwise routes immediately via InboundDispatchService
 */
class ProcessInboundMessageJob implements JobInterface
{
    public static int $maxAttempts = 3;
    public static bool $shouldBeUnique = false;

    public function __construct(
        private readonly ChannelRegistry $channels,
        private readonly InboundDispatchService $dispatchService,
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
            return;
        }

        $user = $transport->resolveUserByExternalIdentifier($envelope->externalIdentifier);
        if ($user === null) {
            if (!$transport->requiresVerification()) {
                return;
            }
            $verified = $transport->handleUnverifiedSender($envelope, $agent);
            if ($verified === null) {
                return;
            }
            $user = $verified;
        }

        $session = $this->findOrCreateSession($user, $agent, $envelope);
        $inbound = $this->persistInbound($envelope, $session);
        if ($inbound === null) {
            return; // duplicate by external_message_id
        }

        $session->last_inbound_at = new DateTime();
        TableRegistry::getTableLocator()->get('ChatSessions')->save($session);

        // Audio gets deferred — TranscribeAudioJob downloads the media,
        // runs speech-to-text, updates the message body, then routes via
        // the same InboundDispatchService so the handler sees the transcript
        // as the user's message.
        if ($inbound->content_type === ChatMessage::CONTENT_AUDIO) {
            QueueManager::push(TranscribeAudioJob::class, [
                'message_id' => $inbound->id,
            ]);
            return;
        }

        $this->dispatchService->route($agent, $session, $inbound, $user);
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

        // For audio we persist a placeholder body so chat history shows the
        // turn before transcription completes. TranscribeAudioJob will
        // overwrite content with the real transcript.
        $body = $envelope->body;
        if ($envelope->contentType === ChatMessage::CONTENT_AUDIO && trim($body) === '') {
            $body = '[Audio message — transcribing…]';
        }

        $entity = $messages->newEntity([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_USER,
            'channel' => $envelope->channel,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => $body,
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
