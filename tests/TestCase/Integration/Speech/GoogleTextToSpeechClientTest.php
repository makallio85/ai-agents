<?php
declare(strict_types=1);

namespace App\Test\TestCase\Integration\Speech;

use App\Integration\Speech\GoogleTextToSpeechClient;
use App\Integration\Speech\SpeechException;
use PHPUnit\Framework\TestCase;

class GoogleTextToSpeechClientTest extends TestCase
{
    public function testEncodingResolvesToWhatsappFriendlyDefault(): void
    {
        // No hint -> OGG_OPUS so WhatsApp can ingest the result without re-encoding.
        $this->assertSame(['OGG_OPUS', 'audio/ogg'], GoogleTextToSpeechClient::resolveEncoding(null));
        $this->assertSame(['OGG_OPUS', 'audio/ogg'], GoogleTextToSpeechClient::resolveEncoding('audio/ogg'));
    }

    public function testEncodingMapping(): void
    {
        $this->assertSame(['MP3', 'audio/mpeg'], GoogleTextToSpeechClient::resolveEncoding('audio/mpeg'));
        $this->assertSame(['MP3', 'audio/mpeg'], GoogleTextToSpeechClient::resolveEncoding('audio/mp3'));
        $this->assertSame(['LINEAR16', 'audio/wav'], GoogleTextToSpeechClient::resolveEncoding('audio/wav'));
        $this->assertSame(['MULAW', 'audio/basic'], GoogleTextToSpeechClient::resolveEncoding('audio/mulaw'));
    }

    public function testVoiceForLanguageDeterministic(): void
    {
        $this->assertSame('en-US-Standard-A', GoogleTextToSpeechClient::voiceForLanguage('en-US', 'fallback'));
        $this->assertSame('fi-FI-Standard-A', GoogleTextToSpeechClient::voiceForLanguage('fi-FI', 'fallback'));
        $this->assertSame('fallback', GoogleTextToSpeechClient::voiceForLanguage('xx-YY', 'fallback'));
    }

    public function testSynthesiseWithoutKeyThrows(): void
    {
        $client = new GoogleTextToSpeechClient(apiKey: '');
        $this->expectException(SpeechException::class);
        $client->synthesise('hello');
    }
}
