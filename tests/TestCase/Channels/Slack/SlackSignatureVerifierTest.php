<?php
declare(strict_types=1);

namespace App\Test\TestCase\Channels\Slack;

use App\Channels\Slack\SlackSignatureVerifier;
use PHPUnit\Framework\TestCase;

/**
 * Pure-function test — no DB, no network. Verifies Slack's
 *   "v0:" + timestamp + ":" + body
 * HMAC scheme and the request-replay cutoff.
 */
class SlackSignatureVerifierTest extends TestCase
{
    private const SECRET = 'shared-signing-secret';
    private const NOW = 1_700_000_000;

    private function makeSignature(string $body, int $timestamp, string $secret = self::SECRET): string
    {
        return 'v0=' . hash_hmac('sha256', 'v0:' . $timestamp . ':' . $body, $secret);
    }

    public function testValidSignatureWithinToleranceReturnsTrue(): void
    {
        $verifier = new SlackSignatureVerifier(toleranceSeconds: 300, now: self::NOW);
        $body = '{"type":"event_callback"}';
        $sig = $this->makeSignature($body, self::NOW);

        $this->assertTrue($verifier->verify(self::SECRET, $body, $sig, (string)self::NOW));
    }

    public function testWrongSignatureReturnsFalse(): void
    {
        $verifier = new SlackSignatureVerifier(toleranceSeconds: 300, now: self::NOW);
        $this->assertFalse($verifier->verify(self::SECRET, '{"x":1}', 'v0=' . str_repeat('0', 64), (string)self::NOW));
    }

    public function testTimestampOutsideToleranceReturnsFalse(): void
    {
        $verifier = new SlackSignatureVerifier(toleranceSeconds: 300, now: self::NOW);
        $oldTimestamp = self::NOW - 600; // 10 minutes ago
        $body = '{"type":"event_callback"}';
        $sig = $this->makeSignature($body, $oldTimestamp);

        $this->assertFalse($verifier->verify(self::SECRET, $body, $sig, (string)$oldTimestamp));
    }

    public function testFutureTimestampOutsideToleranceReturnsFalse(): void
    {
        $verifier = new SlackSignatureVerifier(toleranceSeconds: 300, now: self::NOW);
        $futureTimestamp = self::NOW + 600;
        $body = '{"x":1}';
        $sig = $this->makeSignature($body, $futureTimestamp);

        $this->assertFalse($verifier->verify(self::SECRET, $body, $sig, (string)$futureTimestamp));
    }

    public function testMissingTimestampReturnsFalse(): void
    {
        $verifier = new SlackSignatureVerifier(toleranceSeconds: 300, now: self::NOW);
        $body = '{"x":1}';
        $this->assertFalse($verifier->verify(self::SECRET, $body, 'v0=' . hash_hmac('sha256', $body, self::SECRET), null));
        $this->assertFalse($verifier->verify(self::SECRET, $body, 'v0=' . hash_hmac('sha256', $body, self::SECRET), 'not-a-number'));
    }

    public function testWrongPrefixReturnsFalse(): void
    {
        $verifier = new SlackSignatureVerifier(toleranceSeconds: 300, now: self::NOW);
        $body = '{"x":1}';
        $sigWithoutPrefix = hash_hmac('sha256', 'v0:' . self::NOW . ':' . $body, self::SECRET);
        $this->assertFalse($verifier->verify(self::SECRET, $body, $sigWithoutPrefix, (string)self::NOW));
        $this->assertFalse($verifier->verify(self::SECRET, $body, 'sha256=' . $sigWithoutPrefix, (string)self::NOW));
    }

    public function testTamperedBodyFails(): void
    {
        $verifier = new SlackSignatureVerifier(toleranceSeconds: 300, now: self::NOW);
        $original = '{"x":1}';
        $tampered = '{"x":2}';
        $sig = $this->makeSignature($original, self::NOW);
        $this->assertFalse($verifier->verify(self::SECRET, $tampered, $sig, (string)self::NOW));
    }

    public function testWrongSecretFails(): void
    {
        $verifier = new SlackSignatureVerifier(toleranceSeconds: 300, now: self::NOW);
        $body = '{"x":1}';
        $sig = $this->makeSignature($body, self::NOW, 'a-different-secret');
        $this->assertFalse($verifier->verify(self::SECRET, $body, $sig, (string)self::NOW));
    }
}
