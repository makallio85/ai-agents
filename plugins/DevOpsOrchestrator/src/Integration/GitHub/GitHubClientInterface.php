<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Integration\GitHub;

interface GitHubClientInterface
{
    /**
     * Create an issue in the repository.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed> Created issue data (number, html_url, etc.)
     * @throws \DevOpsOrchestrator\Integration\GitHub\GitHubException
     */
    public function createIssue(string $owner, string $repo, array $payload): array;

    /**
     * Ensure labels exist on the repository. Creates missing ones.
     *
     * @param list<string> $labelSlugs
     */
    public function ensureLabels(string $owner, string $repo, array $labelSlugs): void;

    /**
     * Get all labels for a repository.
     *
     * @return list<array<string, mixed>>
     */
    public function getLabels(string $owner, string $repo): array;

    /**
     * List repositories accessible to the authenticated token.
     *
     * @return list<array<string, mixed>>
     */
    public function listRepos(): array;

    /**
     * Get the decoded contents of a file in a repository.
     *
     * @return array<string, mixed> GitHub contents response including 'content' (base64) and 'sha'.
     * @throws \DevOpsOrchestrator\Integration\GitHub\GitHubException When the file is not found.
     */
    public function getFileContents(string $owner, string $repo, string $path): array;

    /**
     * Create or update a file in a repository (single-file commit).
     *
     * @param array<string, mixed> $payload Must include 'message', 'content' (base64), and 'sha' when updating.
     * @return array<string, mixed> GitHub commit response.
     */
    public function createOrUpdateFile(string $owner, string $repo, string $path, array $payload): array;

    /**
     * Create a pull request.
     *
     * @param array<string, mixed> $payload Must include 'title', 'head', 'base'. Optional: 'body'.
     * @return array<string, mixed> Created PR data (number, html_url, etc.)
     */
    public function createPullRequest(string $owner, string $repo, array $payload): array;

    /**
     * Add a comment to an issue or pull request.
     *
     * @return array<string, mixed> Created comment data.
     */
    public function commentOnIssue(string $owner, string $repo, int $issueNumber, string $body): array;

    /**
     * Close an issue.
     *
     * @return array<string, mixed> Updated issue data.
     */
    public function closeIssue(string $owner, string $repo, int $issueNumber): array;

    /**
     * List pull requests for a repository.
     *
     * @param string $state 'open', 'closed', or 'all'
     * @return list<array<string, mixed>> Pull request data.
     */
    public function listPullRequests(string $owner, string $repo, string $state = 'open'): array;

    /**
     * Get a single pull request with full metadata.
     *
     * @return array<string, mixed> Pull request data including head, base, body, etc.
     */
    public function getPullRequest(string $owner, string $repo, int $number): array;

    /**
     * List files changed in a pull request with patch diffs.
     *
     * @return list<array<string, mixed>> Each entry has filename, status, additions, deletions, patch.
     */
    public function getPullRequestFiles(string $owner, string $repo, int $number): array;

    /**
     * Get a list of commits in a pull request.
     *
     * @return list<array<string, mixed>> Commit data.
     */
    public function getPullRequestCommits(string $owner, string $repo, int $number): array;
}
