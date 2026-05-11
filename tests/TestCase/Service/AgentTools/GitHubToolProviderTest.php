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
        $this->assertCount(11, $defs);
    }

    public function testGetDefinitionsContainsExpectedToolNames(): void
    {
        $defs = $this->provider->getDefinitions();
        $names = array_map(fn($d) => $d->name, $defs);

        $this->assertContains('github_list_repos', $names);
        $this->assertContains('github_get_file', $names);
        $this->assertContains('github_create_or_update_file', $names);
        $this->assertContains('github_create_issue', $names);
        $this->assertContains('github_comment_on_issue', $names);
        $this->assertContains('github_close_issue', $names);
        $this->assertContains('github_create_pull_request', $names);
        $this->assertContains('github_list_pull_requests', $names);
        $this->assertContains('github_get_pull_request', $names);
        $this->assertContains('github_get_pull_request_files', $names);
        $this->assertContains('github_get_pull_request_commits', $names);
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

    // ── dispatch() — github_create_or_update_file ─────────────────────────────

    public function testDispatchCreateOrUpdateFileBase64EncodesContent(): void
    {
        $this->github->expects($this->once())
            ->method('createOrUpdateFile')
            ->with(
                'acme',
                'app',
                'src/foo.php',
                $this->callback(function (array $payload) {
                    // content must be base64-encoded
                    return $payload['content'] === base64_encode('<?php echo 1;')
                        && $payload['message'] === 'Add foo';
                }),
            )
            ->willReturn(['commit' => ['sha' => 'commit_sha_xyz']]);

        $result = $this->provider->dispatch('github_create_or_update_file', [
            'owner' => 'acme',
            'repo' => 'app',
            'path' => 'src/foo.php',
            'content' => '<?php echo 1;',
            'message' => 'Add foo',
        ]);

        $this->assertStringContainsString('commit_sha_xyz', $result);
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

    // ── dispatch() — github_create_pull_request ───────────────────────────────

    public function testDispatchCreatePullRequestReturnsNumberAndUrl(): void
    {
        $this->github->expects($this->once())
            ->method('createPullRequest')
            ->willReturn(['number' => 12, 'html_url' => 'https://github.com/acme/app/pull/12']);

        $result = $this->provider->dispatch('github_create_pull_request', [
            'owner' => 'acme',
            'repo' => 'app',
            'title' => 'Feature: add login',
            'head' => 'feature/login',
            'base' => 'main',
        ]);

        $this->assertStringContainsString('12', $result);
        $this->assertStringContainsString('https://github.com/acme/app/pull/12', $result);
    }

    // ── dispatch() — unknown tool ─────────────────────────────────────────────

    public function testDispatchUnknownToolThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown tool/');

        $this->provider->dispatch('does_not_exist', []);
    }
}
