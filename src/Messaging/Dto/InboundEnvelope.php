<?php
declare(strict_types=1);

namespace App\Messaging\Dto;

/**
 * Normalised representation of a single inbound message produced by a
 * channel transport's parseInbound().
 *
 * One InboundEvent (raw webhook payload) may yield zero or more InboundEnvelopes
 * — Meta batches multiple messages and status updates into a single POST.
 *
 * externalAccountId selects the agent (e.g. WhatsApp phone_number_id).
 * externalIdentifier identifies the user (e.g. WhatsApp wa_id digits).
 */
class InboundEnvelope
{
    public const KIND_MESSAGE = 'message';
    public const KIND_STATUS = 'status';

    /**
     * @param array<string, mixed> $rawPayload Channel-specific raw fields, persisted in chat_messages.metadata.
     */
    public function __construct(
        public readonly string $channel,
        public readonly string $kind,
        public readonly string $externalAccountId,
        public readonly string $externalIdentifier,
        public readonly string $externalMessageId,
        public readonly string $contentType,
        public readonly string $body,
        public readonly ?string $externalThreadId = null,
        public readonly ?string $mediaUrl = null,
        public readonly ?string $mediaMimeType = null,
        public readonly ?string $statusUpdate = null,
        public readonly array $rawPayload = [],
    ) {
    }
}
