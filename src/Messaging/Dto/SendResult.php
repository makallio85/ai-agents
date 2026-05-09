<?php
declare(strict_types=1);

namespace App\Messaging\Dto;

/**
 * Result of a transport.send() / sendProactive() call.
 *
 * externalMessageId is the provider's identifier (Meta wamid, email Message-ID)
 * which we persist on the chat_messages row so later status callbacks can be
 * matched back to the originating row.
 */
class SendResult
{
    /**
     * @param array<string, mixed> $providerPayload Raw response (logged, not exposed).
     */
    public function __construct(
        public readonly string $externalMessageId,
        public readonly ?string $externalThreadId = null,
        public readonly string $status = 'sent',
        public readonly array $providerPayload = [],
    ) {
    }
}
