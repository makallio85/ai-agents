<?php
declare(strict_types=1);

namespace App\Test\TestCase\Integration\Speech;

use App\Integration\Speech\OpenAiSpeechToTextClient;
use App\Integration\Speech\SpeechException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OpenAiSpeechToTextClient.
 *
 * Pure function tests only — no network calls, per project CLAUDE.md rules.
 * The transcribe() method itself is network-facing and tested only for the
 * fast-fail case (empty API key). All other logic (extension mapping, language
 * code normalisation, response parsing) is exercised via static helpers.
 */
class OpenAiSpeechToTextClientTest extends TestCase
{
    public function testTranscribeWithoutKeyThrows(): void
    {
        $client = new OpenAiSpeechToTextClient(apiKey: '');
        $this->expectException(SpeechException::class);
        $client->transcribe('audio bytes', 'audio/ogg');
    }

    public function testExtensionForCommonMimes(): void
    {
        $this->assertSame('mp3', OpenAiSpeechToTextClient::extensionForMime('audio/mpeg'));
        $this->assertSame('mp3', OpenAiSpeechToTextClient::extensionForMime('audio/mp3'));
        $this->assertSame('wav', OpenAiSpeechToTextClient::extensionForMime('audio/wav'));
        $this->assertSame('wav', OpenAiSpeechToTextClient::extensionForMime('audio/wave'));
        $this->assertSame('flac', OpenAiSpeechToTextClient::extensionForMime('audio/flac'));
        $this->assertSame('ogg', OpenAiSpeechToTextClient::extensionForMime('audio/ogg'));
        $this->assertSame('webm', OpenAiSpeechToTextClient::extensionForMime('audio/webm'));
        $this->assertSame('m4a', OpenAiSpeechToTextClient::extensionForMime('audio/mp4'));
        $this->assertSame('m4a', OpenAiSpeechToTextClient::extensionForMime('audio/m4a'));
    }

    public function testExtensionForCaseInsensitiveMime(): void
    {
        $this->assertSame('mp3', OpenAiSpeechToTextClient::extensionForMime('AUDIO/MPEG'));
        $this->assertSame('ogg', OpenAiSpeechToTextClient::extensionForMime('Audio/Ogg'));
    }

    public function testExtensionForMimeWithParameters(): void
    {
        // Parameters after ';' should be stripped.
        $this->assertSame('ogg', OpenAiSpeechToTextClient::extensionForMime('audio/ogg; codecs=opus'));
    }

    public function testExtensionForUnknownMimeFallsBackToBin(): void
    {
        $this->assertSame('bin', OpenAiSpeechToTextClient::extensionForMime('application/octet-stream'));
        $this->assertSame('bin', OpenAiSpeechToTextClient::extensionForMime(''));
    }

    public function testNormaliseLanguageCodeTruncatesToTwoChars(): void
    {
        // BCP-47 "en-US" → "en" (Whisper uses ISO-639 two-char codes)
        $this->assertSame('en', OpenAiSpeechToTextClient::normaliseLanguageCode('en-US'));
        $this->assertSame('fi', OpenAiSpeechToTextClient::normaliseLanguageCode('fi-FI'));
        // Two-char codes are returned as-is.
        $this->assertSame('en', OpenAiSpeechToTextClient::normaliseLanguageCode('en'));
        // Null stays null.
        $this->assertNull(OpenAiSpeechToTextClient::normaliseLanguageCode(null));
    }

    public function testParseResponseExtractsTranscript(): void
    {
        $body = json_encode(['text' => ' Hello, world. ', 'language' => 'en']);
        $result = OpenAiSpeechToTextClient::parseResponse((string)$body);
        $this->assertSame('Hello, world.', $result->transcript);
        $this->assertNull($result->confidence);   // Whisper does not report confidence
        $this->assertSame('en', $result->detectedLanguage);
    }

    public function testParseResponseHandlesMissingLanguage(): void
    {
        $body = json_encode(['text' => 'Hi']);
        $result = OpenAiSpeechToTextClient::parseResponse((string)$body);
        $this->assertSame('Hi', $result->transcript);
        $this->assertNull($result->detectedLanguage);
    }

    public function testParseResponseHandlesEmptyTranscript(): void
    {
        $body = json_encode(['text' => '']);
        $result = OpenAiSpeechToTextClient::parseResponse((string)$body);
        $this->assertSame('', $result->transcript);
    }
}
