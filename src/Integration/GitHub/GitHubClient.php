<?php
declare(strict_types=1);

namespace App\Integration\GitHub;

use Cake\Log\LogTrait;

/**
 * HTTP client for the GitHub REST API v3.
 *
 * Implements every method on GitHubClientInterface using PHP's native
 * file_get_contents + stream_context_create so no additional HTTP library is
 * required. All write operations are logged via CakePHP's LogTrait.
 *
 * Rate-limit (429) and scope/auth (403) errors are surfaced as GitHubException
 * with the HTTP status code attached so callers can react accordingly — e.g.
 * CreateGitHubIssueJob requeues on 429 but rejects on other errors.
 *
 * The token and base API URL are injected at construction so the client can be
 * pointed at a different endpoint in tests or on-prem GitHub Enterprise.
 */
class GitHubClient implements GitHubClientInterface
{
    use LogTrait;

    private const DEFAULT_API_URL = 'https://api.github.com';

    public function __construct(
        private readonly string $token,
        private readonly string $apiUrl = self::DEFAULT_API_URL
    ) {
    }

    // ── Repositories ─────────────────────────────────────────────────────────

    public function listRepos(): array
    {
        return array_values($this->request('GET', "{$this->apiUrl}/user/repos?per_page=100&sort=updated"));
    }

    public function getRepo(string $owner, string $repo): array
    {
        return $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}");
    }

    public function listBranches(string $owner, string $repo): array
    {
        return array_values($this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/branches?per_page=100"));
    }

    public function createBranch(string $owner, string $repo, string $branch, string $sha): array
    {
        $result = $this->request('POST', "{$this->apiUrl}/repos/{$owner}/{$repo}/git/refs", [
            'ref' => "refs/heads/{$branch}",
            'sha' => $sha,
        ]);
        $this->log("GitHub branch created: {$branch} in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $result;
    }

    public function deleteBranch(string $owner, string $repo, string $branch): void
    {
        $this->request('DELETE', "{$this->apiUrl}/repos/{$owner}/{$repo}/git/refs/heads/{$branch}");
        $this->log("GitHub branch deleted: {$branch} in {$owner}/{$repo}", 'info', ['scope' => 'github']);
    }

    // ── Files ─────────────────────────────────────────────────────────────────

    public function getFileContents(string $owner, string $repo, string $path): array
    {
        return $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/contents/{$path}");
    }

    public function listDirectory(string $owner, string $repo, string $path): array
    {
        return array_values($this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/contents/{$path}"));
    }

    public function createOrUpdateFile(string $owner, string $repo, string $path, array $payload): array
    {
        $result = $this->request('PUT', "{$this->apiUrl}/repos/{$owner}/{$repo}/contents/{$path}", $payload);
        $this->log("GitHub file committed: {$path} in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $result;
    }

    public function deleteFile(string $owner, string $repo, string $path, array $payload): array
    {
        $result = $this->request('DELETE', "{$this->apiUrl}/repos/{$owner}/{$repo}/contents/{$path}", $payload);
        $this->log("GitHub file deleted: {$path} in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $result;
    }

    // ── Issues ────────────────────────────────────────────────────────────────

    public function listIssues(string $owner, string $repo, string $state = 'open'): array
    {
        return array_values(
            $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/issues?state={$state}&per_page=50")
        );
    }

    public function getIssue(string $owner, string $repo, int $issueNumber): array
    {
        return $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/issues/{$issueNumber}");
    }

    public function createIssue(string $owner, string $repo, array $payload): array
    {
        $response = $this->request('POST', "{$this->apiUrl}/repos/{$owner}/{$repo}/issues", $payload);
        $this->log("GitHub issue created: #{$response['number']} in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $response;
    }

    public function updateIssue(string $owner, string $repo, int $issueNumber, array $payload): array
    {
        $response = $this->request('PATCH', "{$this->apiUrl}/repos/{$owner}/{$repo}/issues/{$issueNumber}", $payload);
        $this->log("GitHub issue #{$issueNumber} updated in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $response;
    }

    public function closeIssue(string $owner, string $repo, int $issueNumber): array
    {
        $response = $this->updateIssue($owner, $repo, $issueNumber, ['state' => 'closed']);
        $this->log("GitHub issue #{$issueNumber} closed in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $response;
    }

    // ── Issue comments ────────────────────────────────────────────────────────

    public function commentOnIssue(string $owner, string $repo, int $issueNumber, string $body): array
    {
        $response = $this->request(
            'POST',
            "{$this->apiUrl}/repos/{$owner}/{$repo}/issues/{$issueNumber}/comments",
            ['body' => $body]
        );
        $this->log("GitHub comment added to #{$issueNumber} in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $response;
    }

    public function listIssueComments(string $owner, string $repo, int $issueNumber): array
    {
        return array_values(
            $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/issues/{$issueNumber}/comments?per_page=100")
        );
    }

    public function updateComment(string $owner, string $repo, int $commentId, string $body): array
    {
        $response = $this->request(
            'PATCH',
            "{$this->apiUrl}/repos/{$owner}/{$repo}/issues/comments/{$commentId}",
            ['body' => $body]
        );
        $this->log("GitHub comment #{$commentId} updated in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $response;
    }

    public function deleteComment(string $owner, string $repo, int $commentId): void
    {
        $this->request('DELETE', "{$this->apiUrl}/repos/{$owner}/{$repo}/issues/comments/{$commentId}");
        $this->log("GitHub comment #{$commentId} deleted in {$owner}/{$repo}", 'info', ['scope' => 'github']);
    }

    // ── Labels ────────────────────────────────────────────────────────────────

    public function getLabels(string $owner, string $repo): array
    {
        return array_values($this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/labels?per_page=100"));
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

    public function addLabelsToIssue(string $owner, string $repo, int $issueNumber, array $labels): array
    {
        $response = $this->request(
            'POST',
            "{$this->apiUrl}/repos/{$owner}/{$repo}/issues/{$issueNumber}/labels",
            ['labels' => $labels]
        );
        $this->log("GitHub labels added to #{$issueNumber} in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return array_values($response);
    }

    public function removeLabelFromIssue(string $owner, string $repo, int $issueNumber, string $label): void
    {
        $encoded = rawurlencode($label);
        $this->request('DELETE', "{$this->apiUrl}/repos/{$owner}/{$repo}/issues/{$issueNumber}/labels/{$encoded}");
        $this->log("GitHub label '{$label}' removed from #{$issueNumber} in {$owner}/{$repo}", 'info', ['scope' => 'github']);
    }

    // ── Pull Requests ─────────────────────────────────────────────────────────

    public function listPullRequests(string $owner, string $repo, string $state = 'open'): array
    {
        return array_values(
            $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/pulls?state={$state}&per_page=50")
        );
    }

    public function getPullRequest(string $owner, string $repo, int $number): array
    {
        return $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/pulls/{$number}");
    }

    public function createPullRequest(string $owner, string $repo, array $payload): array
    {
        $response = $this->request('POST', "{$this->apiUrl}/repos/{$owner}/{$repo}/pulls", $payload);
        $this->log("GitHub PR created: #{$response['number']} in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $response;
    }

    public function updatePullRequest(string $owner, string $repo, int $number, array $payload): array
    {
        $response = $this->request('PATCH', "{$this->apiUrl}/repos/{$owner}/{$repo}/pulls/{$number}", $payload);
        $this->log("GitHub PR #{$number} updated in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $response;
    }

    public function mergePullRequest(string $owner, string $repo, int $number, array $payload = []): array
    {
        $response = $this->request('PUT', "{$this->apiUrl}/repos/{$owner}/{$repo}/pulls/{$number}/merge", $payload);
        $this->log("GitHub PR #{$number} merged in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $response;
    }

    public function getPullRequestFiles(string $owner, string $repo, int $number): array
    {
        return array_values(
            $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/pulls/{$number}/files?per_page=100")
        );
    }

    public function getPullRequestCommits(string $owner, string $repo, int $number): array
    {
        return array_values(
            $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/pulls/{$number}/commits?per_page=100")
        );
    }

    // ── PR Reviews ────────────────────────────────────────────────────────────

    public function listPrReviews(string $owner, string $repo, int $number): array
    {
        return array_values(
            $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/pulls/{$number}/reviews")
        );
    }

    public function createPrReview(string $owner, string $repo, int $number, array $payload): array
    {
        $response = $this->request('POST', "{$this->apiUrl}/repos/{$owner}/{$repo}/pulls/{$number}/reviews", $payload);
        $this->log("GitHub review submitted on PR #{$number} in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $response;
    }

    // ── Commits ───────────────────────────────────────────────────────────────

    public function listCommits(string $owner, string $repo, string $branch = ''): array
    {
        $url = "{$this->apiUrl}/repos/{$owner}/{$repo}/commits?per_page=30";
        if ($branch !== '') {
            $url .= '&sha=' . rawurlencode($branch);
        }
        return array_values($this->request('GET', $url));
    }

    public function getCommit(string $owner, string $repo, string $sha): array
    {
        return $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/commits/{$sha}");
    }

    // ── Releases ──────────────────────────────────────────────────────────────

    public function listReleases(string $owner, string $repo): array
    {
        return array_values($this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/releases?per_page=30"));
    }

    public function createRelease(string $owner, string $repo, array $payload): array
    {
        $response = $this->request('POST', "{$this->apiUrl}/repos/{$owner}/{$repo}/releases", $payload);
        $this->log("GitHub release created: {$response['tag_name']} in {$owner}/{$repo}", 'info', ['scope' => 'github']);
        return $response;
    }

    // ── Workflows ─────────────────────────────────────────────────────────────

    public function listWorkflowRuns(string $owner, string $repo, string $workflowId = '', string $branch = ''): array
    {
        if ($workflowId !== '') {
            $url = "{$this->apiUrl}/repos/{$owner}/{$repo}/actions/workflows/{$workflowId}/runs?per_page=20";
        } else {
            $url = "{$this->apiUrl}/repos/{$owner}/{$repo}/actions/runs?per_page=20";
        }
        if ($branch !== '') {
            $url .= '&branch=' . rawurlencode($branch);
        }
        $data = $this->request('GET', $url);
        return array_values((array)($data['workflow_runs'] ?? $data));
    }

    public function getWorkflowRun(string $owner, string $repo, int $runId): array
    {
        return $this->request('GET', "{$this->apiUrl}/repos/{$owner}/{$repo}/actions/runs/{$runId}");
    }

    // ── Search ────────────────────────────────────────────────────────────────

    public function searchIssues(string $query): array
    {
        $url = "{$this->apiUrl}/search/issues?q=" . rawurlencode($query) . '&per_page=20';
        $data = $this->request('GET', $url);
        return array_values((array)($data['items'] ?? []));
    }

    public function searchCode(string $query): array
    {
        $url = "{$this->apiUrl}/search/code?q=" . rawurlencode($query) . '&per_page=20';
        $data = $this->request('GET', $url);
        return array_values((array)($data['items'] ?? []));
    }

    // ── HTTP transport ────────────────────────────────────────────────────────

    /**
     * Executes an HTTP request against the GitHub API.
     *
     * Uses PHP's native file_get_contents + stream context so no additional
     * HTTP library is needed. Handles DELETE responses (204 No Content) that
     * return an empty body. Throws GitHubException for all 4xx/5xx responses
     * with the status code and GitHub error message attached.
     *
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

        $statusCode = 200;
        if (!empty($http_response_header)) {
            preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
            $statusCode = isset($m[1]) ? (int)$m[1] : 200;
        }

        // 204 No Content (DELETE success) — return empty array
        if ($statusCode === 204 || $rawResponse === '') {
            return [];
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
