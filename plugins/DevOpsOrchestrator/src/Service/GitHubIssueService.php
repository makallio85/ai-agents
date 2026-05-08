<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Service;

use App\Service\AgentLogService;
use DevOpsOrchestrator\Dto\GitHubIssueDto;
use DevOpsOrchestrator\Dto\ParsedIssueDto;
use DevOpsOrchestrator\Integration\GitHub\GitHubClientInterface;
use DevOpsOrchestrator\Integration\GitHub\GitHubException;

class GitHubIssueService
{
    public function __construct(
        private readonly GitHubClientInterface $gitHubClient,
        private readonly LabelDetectionService $labelDetectionService,
        private readonly AgentLogService $logService
    ) {
    }

    /**
     * Create a GitHub issue from a parsed issue DTO.
     *
     * @return array<string, mixed> GitHub API response (number, html_url)
     */
    public function createFromParsedIssue(
        ParsedIssueDto $dto,
        string $owner,
        string $repo,
        int $agentId,
        string $executionId,
        ?int $userId = null
    ): array {
        $detectedLabels = $this->labelDetectionService->detect($dto);
        $startTime = microtime(true);

        $this->logService->info(
            $agentId,
            $executionId,
            "Creating GitHub issue: \"{$dto->title}\" in {$owner}/{$repo}",
            ['labels' => $detectedLabels, 'owner' => $owner, 'repo' => $repo],
            $userId
        );

        try {
            $this->gitHubClient->ensureLabels($owner, $repo, $detectedLabels);

            $issueDto = new GitHubIssueDto(
                title: $dto->title,
                body: $this->formatBody($dto),
                labels: $detectedLabels
            );

            $result = $this->gitHubClient->createIssue($owner, $repo, $issueDto->toArray());

            $durationMs = (int)((microtime(true) - $startTime) * 1000);

            $this->logService->success(
                $agentId,
                $executionId,
                "GitHub issue #{$result['number']} created successfully",
                $durationMs,
                ['issue_url' => $result['html_url'] ?? ''],
                $userId
            );

            return $result;
        } catch (GitHubException $e) {
            $this->logService->error(
                $agentId,
                $executionId,
                'GitHub issue creation failed',
                $e->getMessage(),
                ['owner' => $owner, 'repo' => $repo, 'status_code' => $e->getStatusCode()],
                $userId
            );

            throw $e;
        }
    }

    private function formatBody(ParsedIssueDto $dto): string
    {
        $body = $dto->body;

        if (!empty($dto->issueType) && $dto->issueType !== 'general') {
            $body = "**Type:** {$dto->issueType}\n\n" . $body;
        }

        return $body;
    }
}
