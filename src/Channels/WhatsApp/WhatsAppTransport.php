<?php
declare(strict_types=1);

namespace App\Channels\WhatsApp;

use App\Channels\WhatsApp\Service\WhatsAppConfigService;
use App\Channels\WhatsApp\Service\WhatsAppOnboardingService;
use App\Messaging\Contract\ChannelTransportInterface;
use App\Messaging\Dto\InboundEnvelope;
use App\Messaging\Dto\OutboundMessage;
use App\Messaging\Dto\SendResult;
use App\Messaging\Exception\OutsideMessagingWindowException;
use App\Model\Entity\Agent;
use App\Model\Entity\ChatMessage;
use App\Model\Entity\ChatSession;
use App\Model\Entity\InboundEvent;
use App\Model\Entity\User;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Meta WhatsApp Cloud API transport.
 *
 * Owns the WhatsApp-specific concerns: 24h Service window, template-based
 * proactive sends, OTP onboarding for unknown phone numbers, parsing Meta's
 * webhook payload format. Other parts of the messaging core (dispatcher,
 * inbound job, registries) talk to this class through ChannelTransportInterface
 * and remain channel-agnostic.
 */
class WhatsAppTransport implements ChannelTransportInterface
{
    public const CHANNEL = 'whatsapp';

    public function __construct(
        private readonly WhatsAppClientInterface $client,
        private readonly WhatsAppConfigService $configService,
        private readonly WhatsAppOnboardingService $onboardingService,
    ) {
    }

    public function name(): string
    {
        return self::CHANNEL;
    }

    public function send(ChatSession $session, OutboundMessage $message): SendResult
    {
        $config = $this->configService->findConfigByAgentId($session->agent_id);
        if ($config === null || !$config->enabled) {
            throw new WhatsAppException("WhatsApp is not configured / enabled for agent {$session->agent_id}");
        }
        if (!$this->isWithinWindow($session)) {
            throw new OutsideMessagingWindowException(
                "Session {$session->id} is outside the WhatsApp 24h Service window; use proactive() with an approved template instead."
            );
        }
        $waId = $this->resolveRecipient($session);
        $response = $this->client->sendText($config->phoneNumberId, $config->accessToken, $waId, $message->body);
        return $this->buildResult($response);
    }

    public function sendProactive(ChatSession $session, OutboundMessage $message): SendResult
    {
        $config = $this->configService->findConfigByAgentId($session->agent_id);
        if ($config === null || !$config->enabled) {
            throw new WhatsAppException("WhatsApp is not configured / enabled for agent {$session->agent_id}");
        }
        $templateName = (string)($message->metadata['template_name']
            ?? $config->welcomeTemplateName
            ?? '');
        if ($templateName === '') {
            throw new WhatsAppException(
                "Proactive send requires a template_name in OutboundMessage metadata or whatsapp.welcome_template_name on the agent."
            );
        }
        $language = (string)($message->metadata['language'] ?? 'en_US');
        /** @var array<int, array<string, mixed>> $components */
        $components = (array)($message->metadata['components'] ?? []);
        $waId = $this->resolveRecipient($session);

        $response = $this->client->sendTemplate(
            $config->phoneNumberId,
            $config->accessToken,
            $waId,
            $templateName,
            $language,
            $components,
        );
        return $this->buildResult($response);
    }

    public function supportsProactive(): bool
    {
        return true;
    }

    public function requiresVerification(): bool
    {
        return true;
    }

    public function resolveAgentByExternalAccount(string $accountId): ?Agent
    {
        $config = $this->configService->findConfigByPhoneNumberId($accountId);
        return $config?->agent;
    }

    public function resolveUserByExternalIdentifier(string $identifier): ?User
    {
        $normalised = $this->normalisePhone($identifier);
        /** @var User|null $user */
        $user = TableRegistry::getTableLocator()->get('Users')
            ->find()
            ->where(['Users.phone_number' => $normalised])
            ->first();
        return $user;
    }

    public function handleUnverifiedSender(InboundEnvelope $envelope, ?Agent $agent): ?User
    {
        return $this->onboardingService->handle($envelope, $agent);
    }

    public function parseInbound(InboundEvent $event): array
    {
        /** @var array<string, mixed>|null $payload */
        $payload = json_decode((string)$event->payload, true);
        if (!is_array($payload)) {
            return [];
        }
        $envelopes = [];
        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                $phoneNumberId = (string)($value['metadata']['phone_number_id'] ?? '');
                if ($phoneNumberId === '') {
                    continue;
                }

                foreach (($value['messages'] ?? []) as $msg) {
                    $envelopes[] = $this->envelopeFromMessage($msg, $phoneNumberId);
                }
                foreach (($value['statuses'] ?? []) as $status) {
                    $envelopes[] = $this->envelopeFromStatus($status, $phoneNumberId);
                }
            }
        }
        return array_values(array_filter($envelopes));
    }

    /** @param array<string, mixed> $msg */
    private function envelopeFromMessage(array $msg, string $phoneNumberId): ?InboundEnvelope
    {
        $waId = $this->normalisePhone((string)($msg['from'] ?? ''));
        $externalMessageId = (string)($msg['id'] ?? '');
        if ($waId === '' || $externalMessageId === '') {
            return null;
        }
        $type = (string)($msg['type'] ?? 'text');
        $body = '';
        $mediaUrl = null;
        $mediaMime = null;
        $contentType = ChatMessage::CONTENT_TEXT;

        if ($type === 'text') {
            $body = (string)($msg['text']['body'] ?? '');
        } elseif (in_array($type, ['image', 'audio', 'document', 'video'], true)) {
            $contentType = $type === 'video' ? ChatMessage::CONTENT_DOCUMENT : $type;
            $body = (string)($msg[$type]['caption'] ?? '');
            $mediaUrl = $msg[$type]['id'] ?? null; // Meta gives a media id; downloading deferred to v2
            $mediaMime = $msg[$type]['mime_type'] ?? null;
        } elseif ($type === 'interactive') {
            // Button reply or list reply — treat as plain text body
            $body = (string)($msg['interactive']['button_reply']['title']
                ?? $msg['interactive']['list_reply']['title']
                ?? '');
        }

        return new InboundEnvelope(
            channel: self::CHANNEL,
            kind: InboundEnvelope::KIND_MESSAGE,
            externalAccountId: $phoneNumberId,
            externalIdentifier: $waId,
            externalMessageId: $externalMessageId,
            contentType: $contentType,
            body: $body,
            externalThreadId: null,
            mediaUrl: $mediaUrl,
            mediaMimeType: $mediaMime,
            statusUpdate: null,
            rawPayload: $msg,
        );
    }

    /** @param array<string, mixed> $status */
    private function envelopeFromStatus(array $status, string $phoneNumberId): ?InboundEnvelope
    {
        $statusValue = (string)($status['status'] ?? '');
        $messageId = (string)($status['id'] ?? '');
        $recipientId = (string)($status['recipient_id'] ?? '');
        if ($statusValue === '' || $messageId === '') {
            return null;
        }
        $mapped = match ($statusValue) {
            'sent' => ChatMessage::STATUS_SENT,
            'delivered' => ChatMessage::STATUS_DELIVERED,
            'read' => ChatMessage::STATUS_READ,
            'failed' => ChatMessage::STATUS_FAILED,
            default => null,
        };
        if ($mapped === null) {
            return null;
        }
        return new InboundEnvelope(
            channel: self::CHANNEL,
            kind: InboundEnvelope::KIND_STATUS,
            externalAccountId: $phoneNumberId,
            externalIdentifier: $this->normalisePhone($recipientId),
            externalMessageId: $messageId,
            contentType: ChatMessage::CONTENT_TEXT,
            body: '',
            externalThreadId: null,
            mediaUrl: null,
            mediaMimeType: null,
            statusUpdate: $mapped,
            rawPayload: $status,
        );
    }

    private function isWithinWindow(ChatSession $session): bool
    {
        if ($session->last_inbound_at === null) {
            return false;
        }
        $hours = (int)Configure::read('Channels.whatsapp.windowHours', 24);
        $threshold = (new DateTime())->modify("-{$hours} hours");
        return $session->last_inbound_at >= $threshold;
    }

    private function resolveRecipient(ChatSession $session): string
    {
        if (!empty($session->channel_external_id)) {
            return $this->stripPlus($session->channel_external_id);
        }
        // Fallback to user's phone_number if external id wasn't recorded.
        $sessions = TableRegistry::getTableLocator()->get('ChatSessions');
        $loaded = $sessions->find()->contain(['Users'])->where(['ChatSessions.id' => $session->id])->first();
        $phone = $loaded->user->phone_number ?? '';
        if ($phone === '') {
            throw new WhatsAppException("Cannot determine WhatsApp recipient for session {$session->id}");
        }
        return $this->stripPlus($phone);
    }

    /** Meta accepts wa_ids in digits-only form. */
    private function stripPlus(string $phone): string
    {
        return ltrim(preg_replace('/[^0-9+]/', '', $phone) ?? '', '+');
    }

    /** Store all phone numbers as +E.164 internally. */
    private function normalisePhone(string $raw): string
    {
        $digits = preg_replace('/[^0-9]/', '', $raw) ?? '';
        return $digits === '' ? '' : '+' . $digits;
    }

    /** @param array<string, mixed> $response */
    private function buildResult(array $response): SendResult
    {
        $messageId = (string)($response['messages'][0]['id'] ?? '');
        return new SendResult(
            externalMessageId: $messageId,
            externalThreadId: null,
            status: ChatMessage::STATUS_SENT,
            providerPayload: $response,
        );
    }
}
