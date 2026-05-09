<?php
declare(strict_types=1);

namespace App\Channels\Slack;

/**
 * Verifies the X-Slack-Signature header on inbound webhooks.
 *
 * Slack's algorithm: sha256 HMAC over the string
 *   "v0:" + X-Slack-Request-Timestamp + ":" + raw_body
 * keyed on the App's signing secret. The output is hex-encoded and
 * prefixed with "v0=" before being placed in the X-Slack-Signature header.
 *
 * Replay protection: requests older than the configured tolerance (default
 * 5 minutes per Slack's recommendation) are rejected even if the signature
 * is otherwise valid.
 */
class SlackSignatureVerifier
{
    private const DEFAULT_TOLERANCE_SECONDS = 300;

    public function __construct(
        private readonly int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        private readonly ?int $now = null,
    ) {
    }

    public function verify(
        string $signingSecret,
        string $rawBody,
        ?string $signatureHeader,
        ?string $timestampHeader,
    ): bool {
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }
        if ($timestampHeader === null || !ctype_digit($timestampHeader)) {
            return false;
        }
        $now = $this->now ?? time();
        if (abs($now - (int)$timestampHeader) > $this->toleranceSeconds) {
            return false;
        }
        if (!str_starts_with($signatureHeader, 'v0=')) {
            return false;
        }
        $provided = substr($signatureHeader, 3);
        $base = 'v0:' . $timestampHeader . ':' . $rawBody;
        $expected = hash_hmac('sha256', $base, $signingSecret);
        return hash_equals($expected, $provided);
    }
}
