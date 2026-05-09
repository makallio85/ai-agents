<?php
declare(strict_types=1);

namespace App\Integration\Speech;

use Cake\Core\Configure;

/**
 * Google Cloud Speech-to-Text v1 REST client.
 *
 * Authenticates via API key (Configure 'Speech.google.apiKey' /
 * GOOGLE_SPEECH_API_KEY) — production deployments may want to swap to a
 * service-account JWT, which is left as a follow-up. Uses the synchronous
 * `speech:recognize` endpoint. WhatsApp voice notes (~30s) and Slack
 * voice clips (≤60s) fit well within its 60-second sync limit; longer
 * recordings should switch to longrunningrecognize, which we don't need
 * for v1 chat-style usage.
 *
 * Encoding is derived from the supplied MIME type. WhatsApp typically
 * sends OGG_OPUS at 16 kHz for voice notes; Slack file uploads can be
 * any consumer format. The mapping below covers everything we expect
 * from those two channels — unknown types fall through to ENCODING_
 * UNSPECIFIED, which lets Google attempt format auto-detection.
 */
class GoogleSpeechToTextClient implements SpeechToTextInterface
{
    private const DEFAULT_API_URL = 'https://speech.googleapis.com/v1';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiUrl = self::DEFAULT_API_URL,
        private readonly string $defaultLanguageCode = 'en-US',
        private readonly string $model = 'latest_short',
    ) {
    }

    public function transcribe(string $audio, string $mimeType, ?string $languageCode = null): SpeechToTextResult
    {
        if ($this->apiKey === '') {
            throw new SpeechException('Google Speech-to-Text API key is not configured');
        }

        $config = [
            'languageCode' => $languageCode ?? $this->defaultLanguageCode,
            'model' => $this->model,
            'enableAutomaticPunctuation' => true,
        ];
        $encoding = self::mapMimeToEncoding($mimeType);
        if ($encoding !== null) {
            $config['encoding'] = $encoding;
        }

        $payload = [
            'config' => $config,
            'audio' => ['content' => base64_encode($audio)],
        ];

        $url = rtrim($this->apiUrl, '/') . '/speech:recognize?key=' . urlencode($this->apiKey);
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
            throw new SpeechException("Speech-to-Text request failed: {$this->apiUrl}");
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
            throw new SpeechException("Speech-to-Text HTTP error {$statusCode}: {$err}", $statusCode);
        }

        $transcript = '';
        $confidences = [];
        $detectedLang = null;
        foreach ((array)($data['results'] ?? []) as $result) {
            $alt = $result['alternatives'][0] ?? null;
            if (!is_array($alt)) {
                continue;
            }
            $transcript .= (string)($alt['transcript'] ?? '');
            if (isset($alt['confidence']) && is_numeric($alt['confidence'])) {
                $confidences[] = (float)$alt['confidence'];
            }
            if ($detectedLang === null && !empty($result['languageCode'])) {
                $detectedLang = (string)$result['languageCode'];
            }
        }

        $avgConfidence = $confidences === []
            ? null
            : array_sum($confidences) / count($confidences);

        return new SpeechToTextResult(
            transcript: trim($transcript),
            confidence: $avgConfidence,
            detectedLanguage: $detectedLang,
        );
    }

    /**
     * Maps a MIME type to Google's RecognitionConfig.AudioEncoding enum.
     * Returning null lets Google attempt format auto-detection (works for
     * many container formats but not all encodings).
     */
    public static function mapMimeToEncoding(string $mime): ?string
    {
        $normalised = strtolower(trim($mime));
        // Strip any "; codecs=..." suffix.
        if (($semi = strpos($normalised, ';')) !== false) {
            $normalised = trim(substr($normalised, 0, $semi));
        }
        return match ($normalised) {
            'audio/ogg', 'audio/opus', 'audio/ogg; codecs=opus' => 'OGG_OPUS',
            'audio/wav', 'audio/wave', 'audio/x-wav' => 'LINEAR16',
            'audio/flac', 'audio/x-flac' => 'FLAC',
            'audio/mpeg', 'audio/mp3' => 'MP3',
            'audio/webm' => 'WEBM_OPUS',
            'audio/amr' => 'AMR',
            'audio/amr-wb' => 'AMR_WB',
            // mp4/m4a/aac etc. — Google's sync endpoint can usually auto-detect.
            default => null,
        };
    }

    public static function fromConfigure(): self
    {
        return new self(
            apiKey: (string)Configure::read('Speech.google.apiKey', ''),
            apiUrl: (string)Configure::read('Speech.google.apiUrl', self::DEFAULT_API_URL),
            defaultLanguageCode: (string)Configure::read('Speech.google.defaultLanguageCode', 'en-US'),
            model: (string)Configure::read('Speech.google.model', 'latest_short'),
        );
    }
}
