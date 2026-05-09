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

    /**
     * Two-step media download: first GET /{media_id} to obtain a short-lived
     * URL, then GET that URL with the bearer token. Returns the raw bytes
     * and the provider-reported MIME type.
     *
     * @return array{content: string, mime: string}
     */
    public function downloadMedia(string $accessToken, string $mediaId): array;

    /**
     * Uploads media bytes to Meta and returns the media_id. Used before
     * sending audio / image / document messages — Meta requires the bytes
     * be uploaded first then referenced by id in the outbound message.
     */
    public function uploadMedia(string $phoneNumberId, string $accessToken, string $bytes, string $mime): string;

    /**
     * Sends an audio message referencing a media_id obtained from
     * uploadMedia(). Useful for TTS-generated agent replies on WhatsApp.
     *
     * @return array<string, mixed>
     */
    public function sendAudio(string $phoneNumberId, string $accessToken, string $toWaId, string $mediaId): array;
}
