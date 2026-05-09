<?php
declare(strict_types=1);

namespace App\Channels\WhatsApp;

use Cake\Core\Configure;

/**
 * Concrete Meta WhatsApp Cloud API client.
 *
 * Uses stream_context_create + file_get_contents to match the existing
 * GitHubClient pattern (no Guzzle dependency). Base URL is injectable for tests.
 */
class WhatsAppClient implements WhatsAppClientInterface
{
    private string $apiUrl;

    public function __construct(?string $apiUrl = null)
    {
        $this->apiUrl = $apiUrl
            ?? (string)Configure::read('Channels.whatsapp.apiUrl', 'https://graph.facebook.com/v20.0');
    }

    public function sendText(string $phoneNumberId, string $accessToken, string $toWaId, string $body): array
    {
        return $this->request($accessToken, "/{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ]);
    }

    public function sendTemplate(
        string $phoneNumberId,
        string $accessToken,
        string $toWaId,
        string $templateName,
        string $language,
        array $components = [],
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $toWaId,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
            ],
        ];
        if ($components !== []) {
            $payload['template']['components'] = $components;
        }
        return $this->request($accessToken, "/{$phoneNumberId}/messages", $payload);
    }

    public function markRead(string $phoneNumberId, string $accessToken, string $messageId): void
    {
        $this->request($accessToken, "/{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ]);
    }

    public function uploadMedia(string $phoneNumberId, string $accessToken, string $bytes, string $mime): string
    {
        // Meta's /{phone_number_id}/media endpoint accepts multipart/form-data
        // with three required fields: messaging_product=whatsapp, type=<mime>,
        // and file=<binary>. We construct the body by hand because the rest
        // of the codebase avoids cURL — file_get_contents handles multipart
        // fine as long as we set the boundary explicitly.
        $boundary = '----wa-' . bin2hex(random_bytes(8));
        $body = '';
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"messaging_product\"\r\n\r\nwhatsapp\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"type\"\r\n\r\n{$mime}\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"audio." . self::extensionForMime($mime) . "\"\r\n";
        $body .= "Content-Type: {$mime}\r\n\r\n";
        $body .= $bytes . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $url = rtrim($this->apiUrl, '/') . "/{$phoneNumberId}/media";
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: multipart/form-data; boundary=' . $boundary,
                    'User-Agent: AI-Agents-Platform/1.0',
                ]),
                'content' => $body,
                'ignore_errors' => true,
            ],
        ];
        $raw = @file_get_contents($url, false, stream_context_create($options));
        if ($raw === false) {
            throw new WhatsAppException("WhatsApp media upload failed: {$url}");
        }

        $statusCode = 200;
        if (!empty($http_response_header)) {
            preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
            $statusCode = isset($m[1]) ? (int)$m[1] : 200;
        }
        /** @var array<string, mixed>|null $data */
        $data = json_decode($raw, true);
        if ($statusCode >= 400) {
            $msg = $data['error']['message'] ?? 'Unknown error';
            throw new WhatsAppException("WhatsApp media upload error {$statusCode}: {$msg}", $statusCode);
        }
        $id = (string)($data['id'] ?? '');
        if ($id === '') {
            throw new WhatsAppException('WhatsApp media upload returned no id');
        }
        return $id;
    }

    public function sendAudio(string $phoneNumberId, string $accessToken, string $toWaId, string $mediaId): array
    {
        return $this->request($accessToken, "/{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => 'audio',
            'audio' => ['id' => $mediaId],
        ]);
    }

    private static function extensionForMime(string $mime): string
    {
        return match (strtolower(trim($mime))) {
            'audio/ogg', 'audio/opus' => 'ogg',
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/mp4', 'audio/m4a' => 'm4a',
            'audio/wav' => 'wav',
            default => 'bin',
        };
    }

    public function downloadMedia(string $accessToken, string $mediaId): array
    {
        // Step 1: GET /{media_id} -> { url, mime_type, ... }
        $meta = $this->getJson($accessToken, "/{$mediaId}");
        $url = (string)($meta['url'] ?? '');
        $mime = (string)($meta['mime_type'] ?? 'application/octet-stream');
        if ($url === '') {
            throw new WhatsAppException("WhatsApp media id {$mediaId} returned no download url");
        }

        // Step 2: GET the short-lived URL with the same bearer token. This is
        // a Meta CDN URL — different host than the Graph API. Reusing the
        // request helper would prepend the Graph base URL, so we issue a
        // raw fetch here.
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $accessToken . "\r\n"
                    . 'User-Agent: AI-Agents-Platform/1.0',
                'ignore_errors' => true,
                'follow_location' => 1,
            ],
        ];
        $bytes = @file_get_contents($url, false, stream_context_create($options));
        if ($bytes === false) {
            throw new WhatsAppException("WhatsApp media download failed for {$mediaId}");
        }

        return ['content' => $bytes, 'mime' => $mime];
    }

    /**
     * Helper for GET requests where we need the JSON body (markRead and
     * sendText use POST and discard the body, so they share request()).
     *
     * @return array<string, mixed>
     */
    private function getJson(string $accessToken, string $path): array
    {
        $url = rtrim($this->apiUrl, '/') . $path;
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $accessToken,
                    'User-Agent: AI-Agents-Platform/1.0',
                ]),
                'ignore_errors' => true,
            ],
        ];
        $raw = @file_get_contents($url, false, stream_context_create($options));
        if ($raw === false) {
            throw new WhatsAppException("WhatsApp API GET failed: {$url}");
        }
        $statusCode = 200;
        if (!empty($http_response_header)) {
            preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
            $statusCode = isset($m[1]) ? (int)$m[1] : 200;
        }
        /** @var array<string, mixed>|null $data */
        $data = json_decode($raw, true);
        if ($statusCode >= 400) {
            $msg = $data['error']['message'] ?? 'Unknown WhatsApp API error';
            throw new WhatsAppException("WhatsApp API error {$statusCode}: {$msg}", $statusCode);
        }
        return $data ?? [];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function request(string $accessToken, string $path, array $body): array
    {
        $url = rtrim($this->apiUrl, '/') . $path;
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                    'User-Agent: AI-Agents-Platform/1.0',
                ]),
                'content' => json_encode($body),
                'ignore_errors' => true,
            ],
        ];
        $raw = @file_get_contents($url, false, stream_context_create($options));
        if ($raw === false) {
            throw new WhatsAppException("WhatsApp API request failed: {$url}");
        }

        $statusCode = 200;
        if (!empty($http_response_header)) {
            preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
            $statusCode = isset($m[1]) ? (int)$m[1] : 200;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($raw, true);
        if ($statusCode >= 400) {
            $msg = $data['error']['message'] ?? 'Unknown WhatsApp API error';
            throw new WhatsAppException("WhatsApp API error {$statusCode}: {$msg}", $statusCode);
        }
        return $data ?? [];
    }
}
