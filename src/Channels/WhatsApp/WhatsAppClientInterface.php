<?php
declare(strict_types=1);

namespace App\Channels\WhatsApp;

/**
 * Wraps the Meta WhatsApp Cloud API HTTP layer so that the transport and
 * its tests can swap in an in-memory fake. Per CLAUDE.md tests must not
 * touch the network — every test uses a stub implementation of this interface.
 *
 * All methods take a per-agent access_token because the platform is multi-tenant:
 * each agent has its own WhatsApp number with its own credentials, stored
 * encrypted in agent_contexts and resolved by WhatsAppConfigService.
 */
interface WhatsAppClientInterface
{
    /**
     * Send a free-form text message. Only valid inside the 24h Service window.
     *
     * @return array<string, mixed> Decoded provider response.
     */
    public function sendText(string $phoneNumberId, string $accessToken, string $toWaId, string $body): array;

    /**
     * Send an approved template message. Required outside the 24h window.
     *
     * @param array<int, array<string, mixed>> $components
     * @return array<string, mixed>
     */
    public function sendTemplate(
        string $phoneNumberId,
        string $accessToken,
        string $toWaId,
        string $templateName,
        string $language,
        array $components = [],
    ): array;

    /** Mark an inbound message as read so the user sees the blue ticks. */
    public function markRead(string $phoneNumberId, string $accessToken, string $messageId): void;
}
