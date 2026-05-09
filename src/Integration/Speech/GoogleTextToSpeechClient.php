<?php
declare(strict_types=1);

namespace App\Integration\Speech;

use Cake\Core\Configure;

/**
 * Google Cloud Text-to-Speech v1 REST client.
 *
 * Uses the same API key as the STT client (Configure 'Speech.google.apiKey'
 * / GOOGLE_SPEECH_API_KEY). Defaults to OGG_OPUS output because that's
 * what WhatsApp Cloud API accepts for outbound voice notes; callers can
 * override with audioFormat='audio/mp4' or 'audio/mpeg' for channels that
 * prefer those.
 */
class GoogleTextToSpeechClient implements TextToSpeechInterface
{
    private const DEFAULT_API_URL = 'https://texttospeech.googleapis.com/v1';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiUrl = self::DEFAULT_API_URL,
        private readonly string $defaultLanguageCode = 'en-US',
        private readonly string $defaultVoice = 'en-US-Standard-A',
    ) {
    }

    public function synthesise(string $text, ?string $languageCode = null, ?string $audioFormat = null): TextToSpeechResult
    {
        if ($this->apiKey === '') {
            throw new SpeechException('Google Text-to-Speech API key is not configured');
        }

        [$encoding, $mime] = self::resolveEncoding($audioFormat);
        $language = $languageCode ?? $this->defaultLanguageCode;

        $payload = [
            'input' => ['text' => $text],
            'voice' => [
                'languageCode' => $language,
                'name' => self::voiceForLanguage($language, $this->defaultVoice),
            ],
            'audioConfig' => [
                'audioEncoding' => $encoding,
            ],
        ];

        $url = rtrim($this->apiUrl, '/') . '/text:synthesize?key=' . urlencode($this->apiKey);
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'User-Agent: AI-Agents-Platform/1.0',
                ]),
                'content' => json_encode($payload),
                'ignore_errors' => true,
                'timeout' => 60,
            ],
        ];
        $raw = @file_get_contents($url, false, stream_context_create($options));
        if ($raw === false) {
            throw new SpeechException("Text-to-Speech request failed: {$this->apiUrl}");
        }

        $statusCode = 200;
        if (!empty($http_response_header)) {
            preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
            $statusCode = isset($m[1]) ? (int)$m[1] : 200;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($raw, true);
        if ($statusCode >= 400) {
            $err = $data['error']['message'] ?? 'unknown error';
            throw new SpeechException("Text-to-Speech HTTP error {$statusCode}: {$err}", $statusCode);
        }

        $b64 = (string)($data['audioContent'] ?? '');
        if ($b64 === '') {
            throw new SpeechException('Text-to-Speech response had no audioContent');
        }
        $bytes = base64_decode($b64, true);
        if ($bytes === false) {
            throw new SpeechException('Text-to-Speech audioContent was not valid base64');
        }

        return new TextToSpeechResult(audio: $bytes, mime: $mime);
    }

    /**
     * Maps an optional MIME hint to Google's audioEncoding enum + the MIME
     * we'll record on the outbound chat_messages row.
     *
     * @return array{0: string, 1: string} [encoding, mime]
     */
    public static function resolveEncoding(?string $audioFormat): array
    {
        $normalised = strtolower(trim((string)$audioFormat));
        if (($semi = strpos($normalised, ';')) !== false) {
            $normalised = trim(substr($normalised, 0, $semi));
        }
        return match ($normalised) {
            'audio/mpeg', 'audio/mp3' => ['MP3', 'audio/mpeg'],
            'audio/wav', 'audio/wave' => ['LINEAR16', 'audio/wav'],
            'audio/mulaw' => ['MULAW', 'audio/basic'],
            // Default to OGG_OPUS — what WhatsApp expects for inbound-style
            // voice notes and what Slack accepts as a generic upload.
            default => ['OGG_OPUS', 'audio/ogg'],
        };
    }

    /**
     * Picks a sensible default voice for a language. Google's catalogue has
     * many "Standard" voices per locale; we use the first letter of the
     * language to pick a deterministic one, falling back to the configured
     * default if the language isn't known.
     */
    public static function voiceForLanguage(string $language, string $fallback): string
    {
        return match (strtolower(substr($language, 0, 5))) {
            'en-us' => 'en-US-Standard-A',
            'en-gb' => 'en-GB-Standard-A',
            'es-es' => 'es-ES-Standard-A',
            'de-de' => 'de-DE-Standard-A',
            'fr-fr' => 'fr-FR-Standard-A',
            'fi-fi' => 'fi-FI-Standard-A',
            'sv-se' => 'sv-SE-Standard-A',
            default => $fallback,
        };
    }

    public static function fromConfigure(): self
    {
        return new self(
            apiKey: (string)Configure::read('Speech.google.apiKey', ''),
            apiUrl: (string)Configure::read('Speech.google.ttsApiUrl', self::DEFAULT_API_URL),
            defaultLanguageCode: (string)Configure::read('Speech.google.defaultLanguageCode', 'en-US'),
            defaultVoice: (string)Configure::read('Speech.google.defaultVoice', 'en-US-Standard-A'),
        );
    }
}
