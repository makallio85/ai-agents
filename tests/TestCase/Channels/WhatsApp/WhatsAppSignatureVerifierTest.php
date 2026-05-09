<?php
declare(strict_types=1);

namespace App\Test\TestCase\Channels\WhatsApp;

use App\Channels\WhatsApp\WhatsAppSignatureVerifier;
use PHPUnit\Framework\TestCase;

/**
 * Pure-function test — no DB, no network. Verifies the SHA256 HMAC scheme
 * Meta uses for X-Hub-Signature-256.
 */
class WhatsAppSignatureVerifierTest extends TestCase
{
    private WhatsAppSignatureVerifier $verifier;

    protected function setUp(): void
    {
        $this->verifier = new WhatsAppSignatureVerifier();
    }

    public function testValidSignatureReturnsTrue(): void
    {
        $secret = 'shared-app-secret';
        $body = '{"object":"whatsapp_business_account","entry":[]}';
        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);

        $this->assertTrue($this->verifier->verify($secret, $body, $expected));
    }

    public function testWrongSignatureReturnsFalse(): void
    {
        $this->assertFalse($this->verifier->verify('secret', 'body', 'sha256=' . str_repeat('0', 64)));
    }

    public function testMissingSignatureReturnsFalse(): void
    {
        $this->assertFalse($this->verifier->verify('secret', 'body', null));
        $this->assertFalse($this->verifier->verify('secret', 'body', ''));
    }

    public function testWrongPrefixReturnsFalse(): void
    {
        $valid = hash_hmac('sha256', 'body', 'secret');
        $this->assertFalse($this->verifier->verify('secret', 'body', 'sha1=' . $valid));
        $this->assertFalse($this->verifier->verify('secret', 'body', $valid));
    }

    public function testTamperedBodyReturnsFalse(): void
    {
        $secret = 'secret';
        $original = '{"a":1}';
        $tampered = '{"a":2}';
        $sig = 'sha256=' . hash_hmac('sha256', $original, $secret);
        $this->assertFalse($this->verifier->verify($secret, $tampered, $sig));
    }
}
