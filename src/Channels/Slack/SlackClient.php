<?php
declare(strict_types=1);

namespace App\Channels\Slack;

use Cake\Core\Configure;

/**
 * Concrete Slack Web API client.
 *
 * Uses stream_context_create + file_get_contents to match the existing
 * GitHubClient / WhatsAppClient pattern (no Guzzle). Base URL is injectable
 * for tests. Slack returns 200 with `{ "ok": false, "error": "..." }` on
 * application errors, so we check the `ok` field rather than the HTTP
 * status alone.
 */
class SlackClient implements SlackClientInterface
{
    private string $apiUrl;

    public function __construct(?string $apiUrl = null)
    {
        $this->apiUrl = $apiUrl
            ?? (string)Configure::read('Channels.slack.apiUrl', 'https://slack.com/api');
    }

    public function postMessage(string $botToken, string $channelId, string $text, ?string $threadTs = null): array
    {
        $payload = [
            'channel' => $channelId,
            'text' => $text,
        ];
        if ($threadTs !== null) {
            $payload['thread_ts'] = $threadTs;
        }
        return $this->request($botToken, '/chat.postMessage', $payload);
    }

    public function openConversation(string $botToken, string $slackUserId): string
    {
        $response = $this->request($botToken, '/conversations.open', ['users' => $slackUserId]);
        $channelId = $response['channel']['id'] ?? null;
        if (!is_string($channelId) || $channelId === '') {
            throw new SlackException('conversations.open returned no channel id');
        }
        return $channelId;
    }

    public function getUserInfo(string $botToken, string $slackUserId): array
    {
        $response = $this->request($botToken, '/users.info?user=' . urlencode($slackUserId), null, 'GET');
        $user = $response['user'] ?? [];
        return [
            'id' => (string)($user['id'] ?? $slackUserId),
            'name' => (string)($user['name'] ?? ''),
            'real_name' => $user['real_name'] ?? null,
            'email' => $user['profile']['email'] ?? null,
            'team_id' => $user['team_id'] ?? null,
        ];
    }

    public function downloadFile(string $botToken, string $url): array
    {
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $botToken . "\r\n"
                    . 'User-Agent: AI-Agents-Platform/1.0',
                'ignore_errors' => true,
                'follow_location' => 1,
            ],
        ];
        $bytes = @file_get_contents($url, false, stream_context_create($options));
        if ($bytes === false) {
            throw new SlackException("Slack file download failed: {$url}");
        }
        $statusCode = 200;
        $mime = 'application/octet-stream';
        if (!empty($http_response_header)) {
            preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
            $statusCode = isset($m[1]) ? (int)$m[1] : 200;
            foreach ($http_response_header as $header) {
                if (stripos($header, 'content-type:') === 0) {
                    $mime = trim(substr($header, strlen('content-type:')));
                    break;
                }
            }
        }
        if ($statusCode >= 400) {
            throw new SlackException("Slack file download HTTP error {$statusCode}", $statusCode);
        }
        return ['content' => $bytes, 'mime' => $mime];
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(string $botToken, string $path, ?array $body, string $method = 'POST'): array
    {
        $url = rtrim($this->apiUrl, '/') . $path;
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $botToken,
                    'Content-Type: application/json; charset=utf-8',
                    'User-Agent: AI-Agents-Platform/1.0',
                ]),
                'ignore_errors' => true,
            ],
        ];
        if ($method !== 'GET' && $body !== null) {
            $options['http']['content'] = json_encode($body);
        }
        $raw = @file_get_contents($url, false, stream_context_create($options));
        if ($raw === false) {
            throw new SlackException("Slack API request failed: {$url}");
        }

        $statusCode = 200;
        if (!empty($http_response_header)) {
            preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
            $statusCode = isset($m[1]) ? (int)$m[1] : 200;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($raw, true);
        if ($statusCode >= 400) {
            throw new SlackException("Slack API HTTP error {$statusCode}", $statusCode);
        }
        if (!isset($data['ok']) || $data['ok'] !== true) {
            $err = (string)($data['error'] ?? 'unknown_error');
            throw new SlackException("Slack API error: {$err}");
        }
        return $data;
    }
}
