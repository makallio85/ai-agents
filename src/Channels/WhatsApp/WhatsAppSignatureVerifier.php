<?php
declare(strict_types=1);

namespace App\Channels\WhatsApp;

/**
 * Verifies the X-Hub-Signature-256 header that Meta attaches to webhook
 * deliveries.
 *
 * Meta computes HMAC-SHA256 over the **raw** request body using the App's
 * shared secret. We must hash the same raw bytes the controller received,
 * before any JSON decoding or middleware mutation, otherwise the signatures
 * won't match. hash_equals is used to avoid timing attacks.
 */
class WhatsAppSignatureVerifier
{
    /**
     * @param string $appSecret Meta App secret (NOT the access token).
     * @param string $rawBody Exact bytes Meta sent.
     * @param string|null $signatureHeader Value of X-Hub-Signature-256 (e.g. "sha256=abcd...").
     */
    public function verify(string $appSecret, string $rawBody, ?string $signatureHeader): bool
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }
        if (!str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }
        $provided = substr($signatureHeader, 7);
        $expected = hash_hmac('sha256', $rawBody, $appSecret);
        return hash_equals($expected, $provided);
    }
}
