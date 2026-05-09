<?php
declare(strict_types=1);

namespace App\Test\TestCase\Integration\Speech;

use App\Integration\Speech\GoogleSpeechToTextClient;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the encoding-mapping helper. Pure function — no DB, no
 * network. The encoding hint is the only piece of provider-specific
 * logic worth testing without hitting Google's API.
 */
class GoogleSpeechToTextClientTest extends TestCase
{
    public function testMapsCommonMimes(): void
    {
        $this->assertSame('OGG_OPUS', GoogleSpeechToTextClient::mapMimeToEncoding('audio/ogg'));
        $this->assertSame('OGG_OPUS', GoogleSpeechToTextClient::mapMimeToEncoding('audio/opus'));
        $this->assertSame('OGG_OPUS', GoogleSpeechToTextClient::mapMimeToEncoding('audio/ogg; codecs=opus'));
        $this->assertSame('LINEAR16', GoogleSpeechToTextClient::mapMimeToEncoding('audio/wav'));
        $this->assertSame('FLAC', GoogleSpeechToTextClient::mapMimeToEncoding('audio/flac'));
        $this->assertSame('MP3', GoogleSpeechToTextClient::mapMimeToEncoding('audio/mpeg'));
        $this->assertSame('WEBM_OPUS', GoogleSpeechToTextClient::mapMimeToEncoding('audio/webm'));
        $this->assertSame('AMR', GoogleSpeechToTextClient::mapMimeToEncoding('audio/amr'));
    }

    public function testHandlesParametersAndCasing(): void
    {
        // Case-insensitive and tolerant of charset / codec parameters.
        $this->assertSame('OGG_OPUS', GoogleSpeechToTextClient::mapMimeToEncoding('AUDIO/OGG'));
        $this->assertSame('MP3', GoogleSpeechToTextClient::mapMimeToEncoding('Audio/MP3'));
    }

    public function testUnknownMimeReturnsNull(): void
    {
        // Returning null lets Google attempt format auto-detection.
        $this->assertNull(GoogleSpeechToTextClient::mapMimeToEncoding('audio/m4a'));
        $this->assertNull(GoogleSpeechToTextClient::mapMimeToEncoding('application/octet-stream'));
        $this->assertNull(GoogleSpeechToTextClient::mapMimeToEncoding(''));
    }

    public function testTranscribeWithoutKeyThrows(): void
    {
        $client = new GoogleSpeechToTextClient(apiKey: '');
        $this->expectException(\App\Integration\Speech\SpeechException::class);
        $client->transcribe('bytes', 'audio/ogg');
    }
}
