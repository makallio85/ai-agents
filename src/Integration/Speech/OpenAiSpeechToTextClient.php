<?php
declare(strict_types=1);

namespace App\Integration\Speech;

use Cake\Core\Configure;

/**
 * OpenAI Whisper speech-to-text client.
 *
 * Sends audio to OpenAI's `/v1/audio/transcriptions` endpoint (Whisper model)
 * and returns a normalised SpeechToTextResult. Chosen as the primary
 * SpeechToTextInterface implementation because the project already has an
 * OPENAI_API_KEY configured — no additional vendor account is required.
 *
 * The Whisper API accepts multipart/form-data with the audio file attached.
 * Because CURLFile requires a filesystem path, the raw audio bytes are written
 * to a temp file, the request is made, and the file is deleted in a finally
 * block to avoid leaking temp files on failure.
 *
 * Whisper does not return per-segment confidence scores, so
 * SpeechToTextResult::$confidence is always null.
 *
 * Language codes: the application uses BCP-47 ("en-US") but Whisper expects
 * ISO-639-1 two-character codes ("en"). normaliseLanguageCode() handles the
 * conversion automatically.
 *
 * Called from TranscribeAudioJob after audio is downloaded from the channel
 * transport. The Application DI container resolves SpeechToTextInterface to
 * this class when OPENAI_API_KEY is present, falling back to
 * GoogleSpeechToTextClient otherwise.
 */
class OpenAiSpeechToTextClient implements SpeechToTextInterface
{
    private const API_URL = 'https://api.openai.com/v1/audio/transcriptions';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'whisper-1',
        private readonly string $apiUrl = self::API_URL,
    ) {
    }

    /**
     * Transcribes raw audio bytes via the Whisper API.
     *
     * Writes audio to a temp file so CURLFile can attach it as multipart data,
     * then deletes the temp file regardless of whether the request succeeds.
     *
     * @throws SpeechException On API key missing, write failure, HTTP error.
     */
    public function transcribe(string $audio, string $mimeType, ?string $languageCode = null): SpeechToTextResult
    {
        if ($this->apiKey === '') {
            throw new SpeechException('OpenAI API key is not configured for Whisper transcription');
        }

        $ext = self::extensionForMime($mimeType);
        $tmpPath = tempnam(sys_get_temp_dir(), 'whisper_') . '.' . $ext;
        if (@file_put_contents($tmpPath, $audio) === false) {
            throw new SpeechException('Failed to write audio bytes to temp file for Whisper transcription');
        }

        try {
            return $this->post($tmpPath, $mimeType, self::normaliseLanguageCode($languageCode));
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Performs the multipart/form-data POST to the Whisper transcriptions endpoint.
     *
     * @throws SpeechException On curl failure or non-2xx HTTP response.
     */
    private function post(string $tmpPath, string $mimeType, ?string $languageCode): SpeechToTextResult
    {
        $fields = [
            'file' => new \CURLFile($tmpPath, $mimeType, basename($tmpPath)),
            'model' => $this->model,
            'response_format' => 'json',
        ];
        if ($languageCode !== null) {
            $fields['language'] = $languageCode;
        }

        $curl = curl_init($this->apiUrl);
        if ($curl === false) {
            throw new SpeechException('Failed to initialise curl for Whisper transcription request');
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 120,
        ]);

        $body = curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false) {
            throw new SpeechException('Whisper API request failed: curl returned no response');
        }

        if ($httpCode >= 400) {
            $data = json_decode((string)$body, true);
            $err = (string)($data['error']['message'] ?? $body);
            throw new SpeechException("Whisper API HTTP error {$httpCode}: {$err}", $httpCode);
        }

        return self::parseResponse((string)$body);
    }

    /**
     * Parses a successful Whisper JSON response into a SpeechToTextResult.
     *
     * Exposed as a public static method so the test suite can exercise response
     * parsing without making network calls.
     */
    public static function parseResponse(string $body): SpeechToTextResult
    {
        $data = json_decode($body, true);
        $transcript = trim((string)($data['text'] ?? ''));
        $language = isset($data['language']) ? (string)$data['language'] : null;

        return new SpeechToTextResult(
            transcript: $transcript,
            confidence: null,  // Whisper does not report per-segment confidence
            detectedLanguage: $language !== '' ? $language : null,
        );
    }

    /**
     * Returns the preferred file extension for a MIME type.
     *
     * Whisper's API requires a filename with an extension it recognises so it
     * can infer the container format when encoding is not explicit. Unknown
     * types fall back to 'bin'; Whisper can still attempt auto-detection.
     *
     * Exposed as public static for unit testing.
     */
    public static function extensionForMime(string $mimeType): string
    {
        $normalised = strtolower(trim($mimeType));
        if (($semi = strpos($normalised, ';')) !== false) {
            $normalised = trim(substr($normalised, 0, $semi));
        }
        return match ($normalised) {
            'audio/mpeg', 'audio/mp3'           => 'mp3',
            'audio/wav', 'audio/wave',
            'audio/x-wav'                        => 'wav',
            'audio/flac', 'audio/x-flac'        => 'flac',
            'audio/ogg', 'audio/opus'            => 'ogg',
            'audio/webm'                         => 'webm',
            'audio/amr'                          => 'amr',
            'audio/mp4', 'audio/m4a', 'audio/aac' => 'm4a',
            default                              => 'bin',
        };
    }

    /**
     * Converts a BCP-47 language code ("en-US") to ISO-639-1 ("en").
     *
     * Whisper uses two-character ISO language codes. The application's
     * speech config stores BCP-47 codes, so we take only the first two
     * characters. Returns null when the input is null (let Whisper auto-detect).
     *
     * Exposed as public static for unit testing.
     */
    public static function normaliseLanguageCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        return substr($code, 0, 2);
    }

    /**
     * Builds an instance from the application's Configure / environment.
     *
     * Reads OPENAI_API_KEY via Configure::read('Llm.openaiApiKey') first so
     * that the key can be overridden in app_local.php, falling back to the
     * raw environment variable.
     */
    public static function fromConfigure(): self
    {
        return new self(
            apiKey: (string)(Configure::read('Llm.openaiApiKey') ?? env('OPENAI_API_KEY', '')),
            model: 'whisper-1',
            apiUrl: self::API_URL,
        );
    }
}
