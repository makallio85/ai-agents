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
     * Downloads a private Slack file using a two-step approach.
     *
     * Step 1 — authenticate: GET the url_private_download with the Bearer
     * token. Slack responds with either the file bytes directly (200) or a
     * redirect to a pre-signed CDN URL (302).
     *
     * Step 2 — fetch CDN bytes: download from the redirect target WITHOUT
     * the Authorization header. This is critical: PHP curl forwards all
     * custom HTTPHEADER values (including Authorization) on every redirect,
     * regardless of the target host. Slack's CDN (CloudFront) rejects
     * requests that carry a Slack Bearer token and returns an HTML error
     * page instead of the file. The two-step approach ensures the Bearer
     * header only goes to files.slack.com and is never forwarded to the CDN.
     *
     * @throws SlackException On curl failure or HTTP error.
     * @return array{content: string, mime: string}
     */
    public function downloadFile(string $botToken, string $url): array
    {
        // Step 1: hit the Slack URL with the Bearer token; don't follow
        // redirects so curl never forwards the header to a third-party CDN.
        $curl = curl_init($url);
        if ($curl === false) {
            throw new SlackException('Failed to initialise curl for Slack file download');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $botToken,
                'User-Agent: AI-Agents-Platform/1.0',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HEADER         => true,
        ]);

        $raw = curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $redirectUrl = (string)curl_getinfo($curl, CURLINFO_REDIRECT_URL);
        curl_close($curl);

        if ($raw === false || !is_string($raw)) {
            throw new SlackException("Slack file download failed: no response from {$url}");
        }

        $rawHeaders = substr($raw, 0, $headerSize);
        $body       = substr($raw, $headerSize);
        $mime       = self::extractContentType($rawHeaders);

        // Throw with full diagnostics so TranscribeAudioJob logs the real cause.
        if ($httpCode >= 400) {
            throw new SlackException(
                "Slack file download HTTP {$httpCode} — mime={$mime} redirect={$redirectUrl} body_start=" . bin2hex(substr($body, 0, 16)),
                $httpCode
            );
        }

        // If Slack redirected us to a CDN URL, download from there without
        // the Bearer header. The CDN URL is pre-signed and self-authenticating.
        if ($httpCode >= 300 && $redirectUrl !== '') {
            return $this->curlGet($redirectUrl);
        }

        // Log what Slack actually returned so we can diagnose auth issues.
        // If mime is text/html here Slack returned its login page (HTTP 200)
        // meaning the bot token is missing files:read scope.
        if (stripos($mime, 'text/html') !== false) {
            throw new SlackException(
                "Slack returned HTML instead of audio — HTTP {$httpCode}, redirect_url=" . ($redirectUrl ?: 'none') . ", token_prefix=" . substr($botToken, 0, 12)
            );
        }

        return ['content' => $body, 'mime' => $mime];
    }

    /**
     * Simple unauthenticated GET via curl — used for pre-signed CDN URLs
     * that carry auth in their query string.
     *
     * @return array{content: string, mime: string}
     */
    private function curlGet(string $url): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new SlackException('Failed to initialise curl for CDN file download');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => ['User-Agent: AI-Agents-Platform/1.0'],
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HEADER         => true,
        ]);

        $raw = curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);

        if ($raw === false || !is_string($raw)) {
            throw new SlackException("CDN file download failed for URL: {$url}");
        }
        if ($httpCode >= 400) {
            throw new SlackException("CDN file download HTTP error {$httpCode}", $httpCode);
        }

        $rawHeaders = substr($raw, 0, $headerSize);
        $bytes = substr($raw, $headerSize);
        return ['content' => $bytes, 'mime' => self::extractContentType($rawHeaders)];
    }

    /**
     * Extracts the Content-Type value from a raw HTTP response header block.
     * Returns 'application/octet-stream' when the header is absent.
     */
    private static function extractContentType(string $rawHeaders): string
    {
        foreach (explode("\r\n", $rawHeaders) as $header) {
            if (stripos($header, 'content-type:') === 0) {
                return trim(substr($header, strlen('content-type:')));
            }
        }
        return 'application/octet-stream';
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
