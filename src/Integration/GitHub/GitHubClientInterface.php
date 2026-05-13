<?php
declare(strict_types=1);

namespace App\Integration\GitHub;

/**
 * Contract for all GitHub API operations used across the platform.
 *
 * Centralised in the app layer (not a plugin) so every agent plugin can depend
 * on it without coupling to DevOpsOrchestrator. The concrete GitHubClient
 * implements this interface; tests inject a mock.
 *
 * All methods throw GitHubException on API errors. The status code on the
 * exception indicates the HTTP error class (403 = auth/scope, 404 = not found,
 * 422 = validation, 429 = rate limit).
 */
interface GitHubClientInterface
{
    // ── Repositories ─────────────────────────────────────────────────────────

    /**
     * List repositories accessible to the authenticated token.
     *
     * @return list<array<string, mixed>>
     */
    public function listRepos(): array;

    /**
     * Get metadata for a single repository.
     *
     * @return array<string, mixed>
     */
    public function getRepo(string $owner, string $repo): array;

    /**
     * List branches for a repository.
     *
     * @return list<array<string, mixed>>
     */
    public function listBranches(string $owner, string $repo): array;

    /**
     * Create a branch from an existing SHA or ref.
     *
     * @return array<string, mixed> Created ref data.
     */
    public function createBranch(string $owner, string $repo, string $branch, string $sha): array;

    /**
     * Delete a branch from a repository.
     */
    public function deleteBranch(string $owner, string $repo, string $branch): void;

    // ── Files ─────────────────────────────────────────────────────────────────

    /**
     * Get the decoded contents of a file in a repository.
     *
     * @return array<string, mixed> GitHub contents response including 'content' (base64) and 'sha'.
     * @throws GitHubException When the file is not found.
     */
    public function getFileContents(string $owner, string $repo, string $path): array;

    /**
     * List the contents of a directory in a repository.
     *
     * @return list<array<string, mixed>> Each entry has name, path, type (file/dir), sha, size.
     */
    public function listDirectory(string $owner, string $repo, string $path): array;

    /**
     * Create or update a file in a repository (single-file commit).
     *
     * @param array<string, mixed> $payload Must include 'message', 'content' (base64), and 'sha' when updating.
     * @return array<string, mixed> GitHub commit response.
     */
    public function createOrUpdateFile(string $owner, string $repo, string $path, array $payload): array;

    /**
     * Delete a file from a repository.
     *
     * @param array<string, mixed> $payload Must include 'message' and 'sha' of the file to delete.
     * @return array<string, mixed> GitHub commit response.
     */
    public function deleteFile(string $owner, string $repo, string $path, array $payload): array;

    // ── Issues ────────────────────────────────────────────────────────────────

    /**
     * List issues for a repository.
     *
     * @param string $state 'open', 'closed', or 'all'
     * @return list<array<string, mixed>>
     */
    public function listIssues(string $owner, string $repo, string $state = 'open'): array;

    /**
     * Get a single issue by number.
     *
     * @return array<string, mixed>
     */
    public function getIssue(string $owner, string $repo, int $issueNumber): array;

    /**
     * Create an issue in the repository.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed> Created issue data (number, html_url, etc.)
     * @throws GitHubException
     */
    public function createIssue(string $owner, string $repo, array $payload): array;

    /**
     * Update an existing issue (title, body, state, assignees, labels, milestone).
     *
     * @param array<string, mixed> $payload Fields to update.
     * @return array<string, mixed> Updated issue data.
     */
    public function updateIssue(string $owner, string $repo, int $issueNumber, array $payload): array;

    /**
     * Close an issue.
     *
     * @return array<string, mixed> Updated issue data.
     */
    public function closeIssue(string $owner, string $repo, int $issueNumber): array;

    // ── Issue comments ────────────────────────────────────────────────────────

    /**
     * Add a comment to an issue or pull request.
     *
     * @return array<string, mixed> Created comment data.
     */
    public function commentOnIssue(string $owner, string $repo, int $issueNumber, string $body): array;

    /**
     * List comments on an issue or pull request.
     *
     * @return list<array<string, mixed>>
     */
    public function listIssueComments(string $owner, string $repo, int $issueNumber): array;

    /**
     * Update the body of an existing comment.
     *
     * @return array<string, mixed> Updated comment data.
     */
    public function updateComment(string $owner, string $repo, int $commentId, string $body): array;

    /**
     * Delete a comment from an issue or pull request.
     */
    public function deleteComment(string $owner, string $repo, int $commentId): void;

    // ── Labels ────────────────────────────────────────────────────────────────

    /**
     * Get all labels defined in a repository.
     *
     * @return list<array<string, mixed>>
     */
    public function getLabels(string $owner, string $repo): array;

    /**
     * Ensure labels exist on the repository. Creates missing ones.
     *
     * @param list<string> $labelSlugs
     */
    public function ensureLabels(string $owner, string $repo, array $labelSlugs): void;

    /**
     * Add labels to an issue or pull request.
     *
     * @param list<string> $labels
     * @return list<array<string, mixed>> All labels now on the issue.
     */
    public function addLabelsToIssue(string $owner, string $repo, int $issueNumber, array $labels): array;

    /**
     * Remove a single label from an issue or pull request.
     */
    public function removeLabelFromIssue(string $owner, string $repo, int $issueNumber, string $label): void;

    // ── Pull Requests ─────────────────────────────────────────────────────────

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
     * Create a pull request.
     *
     * @param array<string, mixed> $payload Must include 'title', 'head', 'base'. Optional: 'body'.
     * @return array<string, mixed> Created PR data (number, html_url, etc.)
     */
    public function createPullRequest(string $owner, string $repo, array $payload): array;

    /**
     * Update an existing pull request (title, body, state, base branch).
     *
     * @param array<string, mixed> $payload Fields to update.
     * @return array<string, mixed> Updated PR data.
     */
    public function updatePullRequest(string $owner, string $repo, int $number, array $payload): array;

    /**
     * Merge a pull request.
     *
     * @param array<string, mixed> $payload Optional: 'commit_title', 'commit_message', 'merge_method' (merge/squash/rebase).
     * @return array<string, mixed> Merge result.
     */
    public function mergePullRequest(string $owner, string $repo, int $number, array $payload = []): array;

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

    // ── PR Reviews ────────────────────────────────────────────────────────────

    /**
     * List reviews submitted on a pull request.
     *
     * @return list<array<string, mixed>>
     */
    public function listPrReviews(string $owner, string $repo, int $number): array;

    /**
     * Submit a review on a pull request.
     *
     * @param array<string, mixed> $payload Must include 'event' (APPROVE|REQUEST_CHANGES|COMMENT). Optional: 'body'.
     * @return array<string, mixed> Created review data.
     */
    public function createPrReview(string $owner, string $repo, int $number, array $payload): array;

    // ── Commits ───────────────────────────────────────────────────────────────

    /**
     * List commits on a branch or the default branch.
     *
     * @return list<array<string, mixed>>
     */
    public function listCommits(string $owner, string $repo, string $branch = ''): array;

    /**
     * Get a single commit by SHA.
     *
     * @return array<string, mixed> Commit data including files changed.
     */
    public function getCommit(string $owner, string $repo, string $sha): array;

    // ── Releases ──────────────────────────────────────────────────────────────

    /**
     * List releases for a repository.
     *
     * @return list<array<string, mixed>>
     */
    public function listReleases(string $owner, string $repo): array;

    /**
     * Create a new release.
     *
     * @param array<string, mixed> $payload Must include 'tag_name'. Optional: 'name', 'body', 'draft', 'prerelease'.
     * @return array<string, mixed> Created release data.
     */
    public function createRelease(string $owner, string $repo, array $payload): array;

    // ── Workflows ─────────────────────────────────────────────────────────────

    /**
     * List recent workflow runs for a repository or a specific workflow.
     *
     * @return list<array<string, mixed>>
     */
    public function listWorkflowRuns(string $owner, string $repo, string $workflowId = '', string $branch = ''): array;

    /**
     * Get a single workflow run by ID.
     *
     * @return array<string, mixed>
     */
    public function getWorkflowRun(string $owner, string $repo, int $runId): array;

    // ── Search ────────────────────────────────────────────────────────────────

    /**
     * Search issues and pull requests across GitHub using the search API.
     *
     * @return list<array<string, mixed>> Matched items.
     */
    public function searchIssues(string $query): array;

    /**
     * Search code across GitHub repositories using the search API.
     *
     * @return list<array<string, mixed>> Matched code items.
     */
    public function searchCode(string $query): array;
}
