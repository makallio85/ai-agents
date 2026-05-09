<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Test\TestCase\Service;

use App\Service\AgentLogService;
use Cake\TestSuite\TestCase;
use DevOpsOrchestrator\Dto\ParsedIssueDto;
use DevOpsOrchestrator\Integration\GitHub\GitHubClientInterface;
use DevOpsOrchestrator\Integration\GitHub\GitHubException;
use DevOpsOrchestrator\Service\GitHubIssueService;
use DevOpsOrchestrator\Service\LabelDetectionService;

class GitHubIssueServiceTest extends TestCase
{
    /**
     * @var array<string>
     */
    protected array $fixtures = [
        'app.Agents',
        'app.AgentLogs',
        'app.Labels',
    ];

    private GitHubClientInterface $gitHubMock;
    private LabelDetectionService $labelDetectionMock;
    private AgentLogService $logServiceMock;
    private GitHubIssueService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // All external services mocked — no network calls in tests
        $this->gitHubMock = $this->createMock(GitHubClientInterface::class);
        $this->labelDetectionMock = $this->createMock(LabelDetectionService::class);
        $this->logServiceMock = $this->createMock(AgentLogService::class);

        $this->service = new GitHubIssueService(
            $this->gitHubMock,
            $this->labelDetectionMock,
            $this->logServiceMock
        );
    }

    public function testCreateFromParsedIssueCallsGitHubClient(): void
    {
        $dto = new ParsedIssueDto(
            rawBlock: 'raw',
            title: 'Test issue title',
            body: 'Test issue body',
            issueType: 'bug'
        );

        $this->labelDetectionMock
            ->method('detect')
            ->willReturn(['bug']);

        $this->gitHubMock
            ->expects($this->once())
            ->method('ensureLabels')
            ->with('owner', 'repo', ['bug']);

        $this->gitHubMock
            ->expects($this->once())
            ->method('createIssue')
            ->with('owner', 'repo', $this->arrayHasKey('title'))
            ->willReturn(['number' => 42, 'html_url' => 'https://github.com/owner/repo/issues/42']);

        $result = $this->service->createFromParsedIssue($dto, 'owner', 'repo', 1, 'exec-123');

        $this->assertSame(42, $result['number']);
    }

    public function testCreateFromParsedIssuePropagatesGitHubException(): void
    {
        $dto = new ParsedIssueDto(
            rawBlock: 'raw',
            title: 'Test issue',
            body: 'Test body',
            issueType: 'enhancement'
        );

        $this->labelDetectionMock->method('detect')->willReturn([]);
        $this->gitHubMock->method('ensureLabels');
        $this->gitHubMock->method('createIssue')->willThrowException(
            new GitHubException('API error', 500)
        );

        $this->expectException(GitHubException::class);

        $this->service->createFromParsedIssue($dto, 'owner', 'repo', 1, 'exec-456');
    }

    public function testIssueBodyIncludesTypeWhenNotGeneral(): void
    {
        $dto = new ParsedIssueDto(
            rawBlock: 'raw',
            title: 'Feature request',
            body: 'Please add this feature',
            issueType: 'enhancement'
        );

        $this->labelDetectionMock->method('detect')->willReturn([]);
        $this->gitHubMock->method('ensureLabels');

        $this->gitHubMock
            ->expects($this->once())
            ->method('createIssue')
            ->with(
                'owner',
                'repo',
                $this->callback(function (array $payload): bool {
                    return str_contains($payload['body'], 'enhancement');
                })
            )
            ->willReturn(['number' => 1, 'html_url' => 'https://github.com/owner/repo/issues/1']);

        $this->service->createFromParsedIssue($dto, 'owner', 'repo', 1, 'exec-789');
    }
}
