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
