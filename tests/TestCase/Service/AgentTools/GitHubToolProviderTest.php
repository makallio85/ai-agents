<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\AgentTools;

use App\Service\AgentTools\GitHubToolProvider;
use Cake\TestSuite\TestCase;
use DevOpsOrchestrator\Integration\GitHub\GitHubClientInterface;

/**
 * Unit tests for GitHubToolProvider.
 *
 * Verifies that all tool definitions are registered, that dispatch() routes
 * each tool name to the correct GitHubClientInterface method, and that the
 * return values are formatted as plain-text strings for LLM consumption.
 *
 * The GitHubClientInterface is mocked — no real HTTP calls are made.
 *
 * Write tools (create_or_update_file, create_pull_request) were removed from
 * GitHubToolProvider when the DevOpsOrchestrator bot was scoped to read-only
 * + issue management only. Those tests are removed accordingly.
 */
class GitHubToolProviderTest extends TestCase
{
    private GitHubClientInterface $github;
    private GitHubToolProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->github = $this->createMock(GitHubClientInterface::class);
        $this->provider = new GitHubToolProvider($this->github);
    }

    // ── getDefinitions() ──────────────────────────────────────────────────────

    public function testGetDefinitionsReturnsExpectedToolCount(): void
    {
        $defs = $this->provider->getDefinitions();
        $this->assertCount(14, $defs);
    }

    public function testGetDefinitionsContainsExpectedToolNames(): void
    {
        $defs = $this->provider->getDefinitions();
        $names = array_map(fn($d) => $d->name, $defs);

        $this->assertContains('github_list_repos', $names);
        $this->assertContains('github_get_file', $names);
        $this->assertContains('github_create_issue', $names);
        $this->assertContains('github_comment_on_issue', $names);
        $this->assertContains('github_close_issue', $names);
        $this->assertContains('github_list_issues', $names);
        $this->assertContains('github_get_issue', $names);
        $this->assertContains('github_list_commits', $names);
        $this->assertContains('github_get_commit', $names);
        $this->assertContains('github_list_directory', $names);
        $this->assertContains('github_list_pull_requests', $names);
        $this->assertContains('github_get_pull_request', $names);
        $this->assertContains('github_get_pull_request_files', $names);
        $this->assertContains('github_get_pull_request_commits', $names);

        $this->assertNotContains('github_create_or_update_file', $names);
        $this->assertNotContains('github_create_pull_request', $names);
    }

    // ── dispatch() — github_list_repos ────────────────────────────────────────

    public function testDispatchListReposFormatsRepoNames(): void
    {
        $this->github->expects($this->once())
            ->method('listRepos')
            ->willReturn([
                ['full_name' => 'acme/app'],
                ['full_name' => 'acme/infra'],
            ]);

        $result = $this->provider->dispatch('github_list_repos', []);

        $this->assertStringContainsString('acme/app', $result);
        $this->assertStringContainsString('acme/infra', $result);
    }

    // ── dispatch() — github_get_file ──────────────────────────────────────────

    public function testDispatchGetFileDecodesBase64ContentAndIncludesSha(): void
    {
        $rawContent = 'Hello, World!';
        $this->github->expects($this->once())
            ->method('getFileContents')
            ->with('acme', 'app', 'README.md')
            ->willReturn([
                'content' => base64_encode($rawContent),
                'sha' => 'abc123',
            ]);

        $result = $this->provider->dispatch('github_get_file', [
            'owner' => 'acme',
            'repo' => 'app',
            'path' => 'README.md',
        ]);

        $this->assertStringContainsString('sha:abc123', $result);
        $this->assertStringContainsString($rawContent, $result);
    }

    // ── dispatch() — github_create_issue ─────────────────────────────────────

    public function testDispatchCreateIssueReturnsNumberAndUrl(): void
    {
        $this->github->expects($this->once())
            ->method('createIssue')
            ->willReturn(['number' => 42, 'html_url' => 'https://github.com/acme/app/issues/42']);

        $result = $this->provider->dispatch('github_create_issue', [
            'owner' => 'acme',
            'repo' => 'app',
            'title' => 'Bug: crash on login',
        ]);

        $this->assertStringContainsString('42', $result);
        $this->assertStringContainsString('https://github.com/acme/app/issues/42', $result);
    }

    // ── dispatch() — github_comment_on_issue ─────────────────────────────────

    public function testDispatchCommentOnIssueReturnsCommentUrl(): void
    {
        $this->github->expects($this->once())
            ->method('commentOnIssue')
            ->with('acme', 'app', 7, 'Great catch!')
            ->willReturn(['html_url' => 'https://github.com/acme/app/issues/7#issuecomment-1']);

        $result = $this->provider->dispatch('github_comment_on_issue', [
            'owner' => 'acme',
            'repo' => 'app',
            'issue_number' => 7,
            'body' => 'Great catch!',
        ]);

        $this->assertStringContainsString('https://github.com', $result);
    }

    // ── dispatch() — github_close_issue ──────────────────────────────────────

    public function testDispatchCloseIssueReturnsClosedMessage(): void
    {
        $this->github->expects($this->once())
            ->method('closeIssue')
            ->with('acme', 'app', 5)
            ->willReturn(['number' => 5]);

        $result = $this->provider->dispatch('github_close_issue', [
            'owner' => 'acme',
            'repo' => 'app',
            'issue_number' => 5,
        ]);

        $this->assertStringContainsString('5', $result);
        $this->assertStringContainsString('closed', $result);
    }

    // ── dispatch() — github_list_issues ──────────────────────────────────────

    public function testDispatchListIssuesFormatsIssues(): void
    {
        $this->github->expects($this->once())
            ->method('listIssues')
            ->with('acme', 'app', 'open', null)
            ->willReturn([
                ['number' => 1, 'state' => 'open', 'title' => 'Login bug', 'html_url' => 'https://github.com/acme/app/issues/1'],
                ['number' => 2, 'state' => 'open', 'title' => 'Dark mode', 'html_url' => 'https://github.com/acme/app/issues/2'],
            ]);

        $result = $this->provider->dispatch('github_list_issues', [
            'owner' => 'acme',
            'repo' => 'app',
        ]);

        $this->assertStringContainsString('#1', $result);
        $this->assertStringContainsString('Login bug', $result);
        $this->assertStringContainsString('#2', $result);
    }

    // ── dispatch() — github_get_issue ────────────────────────────────────────

    public function testDispatchGetIssueFormatsIssueDetails(): void
    {
        $this->github->expects($this->once())
            ->method('getIssue')
            ->with('acme', 'app', 3)
            ->willReturn([
                'number' => 3,
                'title' => 'Crash on save',
                'state' => 'open',
                'labels' => [['name' => 'bug']],
                'assignees' => [],
                'comments' => 2,
                'html_url' => 'https://github.com/acme/app/issues/3',
                'body' => 'Steps to reproduce...',
            ]);

        $result = $this->provider->dispatch('github_get_issue', [
            'owner' => 'acme',
            'repo' => 'app',
            'issue_number' => 3,
        ]);

        $this->assertStringContainsString('#3', $result);
        $this->assertStringContainsString('Crash on save', $result);
        $this->assertStringContainsString('bug', $result);
        $this->assertStringContainsString('Steps to reproduce', $result);
    }

    // ── dispatch() — github_list_commits ─────────────────────────────────────

    public function testDispatchListCommitsFormatsCommits(): void
    {
        $this->github->expects($this->once())
            ->method('listCommits')
            ->with('acme', 'app', '', 20)
            ->willReturn([
                [
                    'sha' => 'abc1234def',
                    'commit' => [
                        'message' => 'Fix login bug',
                        'author' => ['name' => 'Alice', 'date' => '2024-01-01'],
                    ],
                ],
            ]);

        $result = $this->provider->dispatch('github_list_commits', [
            'owner' => 'acme',
            'repo' => 'app',
        ]);

        $this->assertStringContainsString('abc1234', $result);
        $this->assertStringContainsString('Fix login bug', $result);
        $this->assertStringContainsString('Alice', $result);
    }

    // ── dispatch() — github_get_commit ───────────────────────────────────────

    public function testDispatchGetCommitFormatsCommitDetails(): void
    {
        $this->github->expects($this->once())
            ->method('getCommit')
            ->with('acme', 'app', 'abc1234')
            ->willReturn([
                'sha' => 'abc1234def',
                'commit' => [
                    'message' => 'Fix login bug',
                    'author' => ['name' => 'Alice', 'email' => 'alice@acme.com', 'date' => '2024-01-01'],
                ],
                'html_url' => 'https://github.com/acme/app/commit/abc1234',
                'files' => [
                    ['filename' => 'src/login.php', 'status' => 'modified', 'additions' => 3, 'deletions' => 1, 'patch' => '@@ -1 +1 @@'],
                ],
            ]);

        $result = $this->provider->dispatch('github_get_commit', [
            'owner' => 'acme',
            'repo' => 'app',
            'sha' => 'abc1234',
        ]);

        $this->assertStringContainsString('Fix login bug', $result);
        $this->assertStringContainsString('src/login.php', $result);
    }

    // ── dispatch() — github_list_directory ───────────────────────────────────

    public function testDispatchListDirectoryFormatsEntries(): void
    {
        $this->github->expects($this->once())
            ->method('listDirectory')
            ->with('acme', 'app', 'src', '')
            ->willReturn([
                ['type' => 'dir', 'path' => 'src/Controller', 'name' => 'Controller'],
                ['type' => 'file', 'path' => 'src/Application.php', 'name' => 'Application.php'],
            ]);

        $result = $this->provider->dispatch('github_list_directory', [
            'owner' => 'acme',
            'repo' => 'app',
            'path' => 'src',
        ]);

        $this->assertStringContainsString('[dir]', $result);
        $this->assertStringContainsString('src/Controller', $result);
        $this->assertStringContainsString('[file]', $result);
        $this->assertStringContainsString('src/Application.php', $result);
    }

    // ── dispatch() — unknown tool ─────────────────────────────────────────────

    public function testDispatchUnknownToolThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown tool/');

        $this->provider->dispatch('does_not_exist', []);
    }
}
