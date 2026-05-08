<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Integration\GitHub;

use Cake\Log\LogTrait;

class GitHubClient implements GitHubClientInterface
{
    use LogTrait;

    private const DEFAULT_API_URL = 'https://api.github.com';

    public function __construct(
        private readonly string $token,
        private readonly string $apiUrl = self::DEFAULT_API_URL
    ) {
    }

    public function createIssue(string $owner, string $repo, array $payload): array
    {
        $url = "{$this->apiUrl}/repos/{$owner}/{$repo}/issues";
        $response = $this->request('POST', $url, $payload);

        $this->log("GitHub issue created: #{$response['number']} in {$owner}/{$repo}", 'info', ['scope' => 'github']);

        return $response;
    }

    public function ensureLabels(string $owner, string $repo, array $labelSlugs): void
    {
        if (empty($labelSlugs)) {
            return;
        }

        $existing = $this->getLabels($owner, $repo);
        $existingNames = array_column($existing, 'name');

        /** @var list<\App\Model\Entity\Label> $labels */
        $labels = \Cake\ORM\TableRegistry::getTableLocator()->get('Labels')
            ->find()->where(['slug IN' => $labelSlugs])->all()->toList();

        foreach ($labels as $label) {
            if (!in_array($label->name, $existingNames, true)) {
                $this->request('POST', "{$this->apiUrl}/repos/{$owner}/{$repo}/labels", [
                    'name' => $label->name,
                    'color' => ltrim($label->color, '#'),
                    'description' => $label->description ?? '',
                ]);
            }
        }
    }

    public function getLabels(string $owner, string $repo): array
    {
        return $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/labels?per_page=100");
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $body = []): array
    {
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", [
                    'Accept: application/vnd.github+json',
                    'Authorization: Bearer ' . $this->token,
                    'Content-Type: application/json',
                    'X-GitHub-Api-Version: 2022-11-28',
                    'User-Agent: AI-Agents-Platform/1.0',
                ]),
                'ignore_errors' => true,
            ],
        ];

        if (!empty($body)) {
            $options['http']['content'] = json_encode($body);
        }

        $context = stream_context_create($options);
        $rawResponse = file_get_contents($url, false, $context);

        if ($rawResponse === false) {
            throw new GitHubException("GitHub API request failed: {$url}");
        }

        // Extract HTTP status from $http_response_header
        $statusCode = 200;
        if (!empty($http_response_header)) {
            preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
            $statusCode = isset($m[1]) ? (int)$m[1] : 200;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($rawResponse, true);

        if ($statusCode === 403 || $statusCode === 429) {
            throw new GitHubException('GitHub rate limit exceeded', $statusCode);
        }

        if ($statusCode >= 400) {
            $errorMsg = $data['message'] ?? 'Unknown error';
            throw new GitHubException("GitHub API error {$statusCode}: {$errorMsg}", $statusCode);
        }

        return $data ?? [];
    }
}
