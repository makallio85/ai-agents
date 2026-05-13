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
        return array_values($this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/labels?per_page=100"));
    }

    public function listRepos(): array
    {
        return array_values($this->request('GET', "{$this->apiUrl}/user/repos?per_page=100&sort=updated"));
    }

    public function getFileContents(string $owner, string $repo, string $path): array
    {
        return $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/contents/{$path}");
    }

    public function createOrUpdateFile(string $owner, string $repo, string $path, array $payload): array
    {
        $url = "{$this->apiUrl}/repos/{$owner}/{$repo}/contents/{$path}";
        $response = $this->request('PUT', $url, $payload);
        $this->log("GitHub file committed: {$path} in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $response;
    }

    public function createPullRequest(string $owner, string $repo, array $payload): array
    {
        $url = "{$this->apiUrl}/repos/{$owner}/{$repo}/pulls";
        $response = $this->request('POST', $url, $payload);
        $this->log("GitHub PR created: #{$response['number']} in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $response;
    }

    public function commentOnIssue(string $owner, string $repo, int $issueNumber, string $body): array
    {
        $url = "{$this->apiUrl}/repos/{$owner}/{$repo}/issues/{$issueNumber}/comments";
        $response = $this->request('POST', $url, ['body' => $body]);
        $this->log("GitHub comment added to #{$issueNumber} in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $response;
    }

    public function closeIssue(string $owner, string $repo, int $issueNumber): array
    {
        $url = "{$this->apiUrl}/repos/{$owner}/{$repo}/issues/{$issueNumber}";
        $response = $this->request('PATCH', $url, ['state' => 'closed']);
        $this->log("GitHub issue #{$issueNumber} closed in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $response;
    }

    public function listPullRequests(string $owner, string $repo, string $state = 'open'): array
    {
        return array_values($this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/pulls?state={$state}&per_page=50"));
    }

    public function getPullRequest(string $owner, string $repo, int $number): array
    {
        return $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/pulls/{$number}");
    }

    public function getPullRequestFiles(string $owner, string $repo, int $number): array
    {
        return array_values($this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/pulls/{$number}/files?per_page=100"));
    }

    public function getPullRequestCommits(string $owner, string $repo, int $number): array
    {
        return array_values($this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/pulls/{$number}/commits?per_page=100"));
    }

    public function listIssues(string $owner, string $repo, string $state = 'open', ?string $labels = null): array
    {
        $url = "{$this->apiUrl}/repos/{$owner}/{$repo}/issues?state={$state}&per_page=50";
        if ($labels !== null && $labels !== '') {
            $url .= '&labels=' . urlencode($labels);
        }
        return array_values($this->request('GET', $url));
    }

    public function getIssue(string $owner, string $repo, int $issueNumber): array
    {
        return $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/issues/{$issueNumber}");
    }

    public function listCommits(string $owner, string $repo, string $branch = '', int $perPage = 30): array
    {
        $url = "{$this->apiUrl}/repos/{$owner}/{$repo}/commits?per_page={$perPage}";
        if ($branch !== '') {
            $url .= '&sha=' . urlencode($branch);
        }
        return array_values($this->request('GET', $url));
    }

    public function getCommit(string $owner, string $repo, string $sha): array
    {
        return $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/commits/{$sha}");
    }

    public function listDirectory(string $owner, string $repo, string $path = '', string $branch = ''): array
    {
        $url = "{$this->apiUrl}/repos/{$owner}/{$repo}/contents/" . ltrim($path, '/');
        if ($branch !== '') {
            $url .= '?ref=' . urlencode($branch);
        }
        return array_values($this->request('GET', $url));
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

        if ($statusCode === 429) {
            $retryAfter = '';
            foreach ($http_response_header as $header) {
                if (stripos($header, 'Retry-After:') === 0) {
                    $retryAfter = ' Retry after ' . trim(substr($header, 12)) . ' seconds.';
                }
            }
            throw new GitHubException("GitHub API error 429: Rate limit exceeded.{$retryAfter}", $statusCode);
        }

        if ($statusCode === 403) {
            $errorMsg = $data['message'] ?? 'Forbidden';
            $hint = stripos($errorMsg, 'accessible') !== false || stripos($errorMsg, 'scope') !== false
                ? ' The token is missing the required scope or repository permission.'
                : '';
            throw new GitHubException("GitHub API error 403: {$errorMsg}.{$hint}", $statusCode);
        }

        if ($statusCode >= 400) {
            $errorMsg = $data['message'] ?? 'Unknown error';
            $details = isset($data['errors']) ? ' Details: ' . json_encode($data['errors']) : '';
            throw new GitHubException("GitHub API error {$statusCode}: {$errorMsg}.{$details}", $statusCode);
        }

        return $data ?? [];
    }
}
