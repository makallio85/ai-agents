<?php
declare(strict_types=1);

namespace App\Channels\Slack;

use Cake\Core\Configure;

/**
 * Concrete Slack Web API client.
 *
 * Uses file_get_contents + stream_context_create for the JSON Slack API
 * endpoints (postMessage, conversations.open, etc.) because they do not
 * redirect. Uses curl for downloadFile() because PHP's stream wrapper does
 * not reliably follow HTTP redirects in CLI/queue-worker mode, causing the
 * HTML redirect body to be returned instead of the audio bytes.
 *
 * Base URL is injectable for tests. Slack returns 200 with
 * `{ "ok": false, "error": "..." }` on application errors, so we check
 * the `ok` field rather than the HTTP status alone.
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

    /**
     * Downloads a private Slack file using the bot token.
     *
     * Uses curl rather than file_get_contents + follow_location because
     * PHP's stream wrapper does not reliably follow HTTP redirects in CLI
     * mode (the queue worker environment). Slack's CDN URLs redirect from
     * slack.com to a CloudFront host; file_get_contents returned the HTML
     * redirect page body instead of the audio bytes, causing Whisper to
     * reject the payload with HTTP 400 "Invalid file format".
     *
     * Curl handles redirects (CURLOPT_FOLLOWLOCATION) correctly in all PHP
     * SAPI modes and is used consistently throughout the rest of the codebase
     * (OpenAiClient, GitHubClient, WhatsAppClient).
     *
     * @throws SlackException On curl failure or HTTP error.
     * @return array{content: string, mime: string}
     */
    public function downloadFile(string $botToken, string $url): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new SlackException('Failed to initialise curl for Slack file download');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $botToken,
                'User-Agent: AI-Agents-Platform/1.0',
            ],
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HEADER         => true,   // include response headers in output
        ]);

        $raw = curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);

        if ($raw === false || !is_string($raw)) {
            throw new SlackException("Slack file download failed for URL: {$url}");
        }

        if ($httpCode >= 400) {
            throw new SlackException("Slack file download HTTP error {$httpCode}", $httpCode);
        }

        // Extract Content-Type from the raw response headers.
        $rawHeaders = substr($raw, 0, $headerSize);
        $bytes = substr($raw, $headerSize);
        $mime = 'application/octet-stream';
        foreach (explode("\r\n", $rawHeaders) as $header) {
            if (stripos($header, 'content-type:') === 0) {
                $mime = trim(substr($header, strlen('content-type:')));
                break;
            }
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
