<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Job;

use App\Service\AgentLogService;
use Cake\ORM\TableRegistry;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use DevOpsOrchestrator\Dto\ParsedIssueDto;
use DevOpsOrchestrator\Integration\GitHub\GitHubClient;
use DevOpsOrchestrator\Integration\GitHub\GitHubException;
use DevOpsOrchestrator\Service\GitHubIssueService;
use DevOpsOrchestrator\Service\LabelDetectionService;
use Interop\Queue\Processor;

/**
 * Creates a single GitHub issue from a parsed issue DTO stored in issue_parsing_jobs.
 *
 * Idempotent: checks if issue is already created before attempting.
 * Retryable: up to $maxAttempts times with automatic backoff by the queue.
 */
class CreateGitHubIssueJob implements JobInterface
{
    public static int $maxAttempts = 3;
    public static bool $shouldBeUnique = false;

    public function __construct(
        private readonly AgentLogService $logService
    ) {
    }

    public function execute(Message $message): ?string
    {
        $payload = $message->getArgument('payload', []);

        $issueParsingJobId = (int)($payload['issue_parsing_job_id'] ?? 0);
        $agentId = (int)($payload['agent_id'] ?? 0);
        $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : null;
        $executionId = (string)($payload['execution_id'] ?? '');
        $githubOwner = (string)($payload['github_owner'] ?? '');
        $githubRepo = (string)($payload['github_repo'] ?? '');
        $githubToken = (string)($payload['github_token'] ?? '');
        $parsedData = (array)($payload['parsed_data'] ?? []);

        if (!$issueParsingJobId || !$githubOwner || !$githubRepo || !$githubToken) {
            return Processor::REJECT;
        }

        $issueParsingJobs = TableRegistry::getTableLocator()->get('IssueParsingJobs');
        $jobEntity = $issueParsingJobs->find()->where(['IssueParsingJobs.id' => $issueParsingJobId])->first();

        if ($jobEntity === null) {
            return Processor::REJECT;
        }

        // Idempotency: already completed
        if ($jobEntity->status === 'completed') {
            return Processor::ACK;
        }

        $jobEntity->status = 'creating';
        $jobEntity->attempts = ($jobEntity->attempts ?? 0) + 1;
        $issueParsingJobs->save($jobEntity);

        $dto = new ParsedIssueDto(
            rawBlock: $jobEntity->raw_block,
            title: (string)($parsedData['title'] ?? ''),
            body: (string)($parsedData['body'] ?? ''),
            issueType: (string)($parsedData['issue_type'] ?? 'general'),
        );

        $gitHubClient = new GitHubClient($githubToken);
        $labelDetection = new LabelDetectionService();
        $service = new GitHubIssueService($gitHubClient, $labelDetection, $this->logService);

        try {
            $result = $service->createFromParsedIssue(
                $dto,
                $githubOwner,
                $githubRepo,
                $agentId,
                $executionId,
                $userId
            );

            $jobEntity->status = 'completed';
            $jobEntity->github_issue_number = $result['number'] ?? null;
            $jobEntity->github_issue_url = $result['html_url'] ?? null;
            $jobEntity->applied_labels = json_encode($labelDetection->detect($dto));
            $issueParsingJobs->save($jobEntity);

            $this->updateConversationProgress($payload['conversation_id'] ?? 0);

            return Processor::ACK;
        } catch (GitHubException $e) {
            $jobEntity->status = 'failed';
            $jobEntity->error_message = $e->getMessage();
            $issueParsingJobs->save($jobEntity);

            if ($e->isRateLimited()) {
                // Requeue with delay for rate limiting
                return Processor::REQUEUE;
            }

            return Processor::REJECT;
        }
    }

    private function updateConversationProgress(int $conversationId): void
    {
        if (!$conversationId) {
            return;
        }

        $conversations = TableRegistry::getTableLocator()->get('Conversations');
        $conversation = $conversations->find()->where(['Conversations.id' => $conversationId])->first();

        if ($conversation === null) {
            return;
        }

        $issueParsingJobs = TableRegistry::getTableLocator()->get('IssueParsingJobs');
        $completedCount = $issueParsingJobs->find()
            ->where(['conversation_id' => $conversationId, 'status' => 'completed'])
            ->count();

        $conversation->blocks_processed = $completedCount;

        if ($completedCount >= $conversation->blocks_found) {
            $conversation->status = 'completed';
        }

        $conversations->save($conversation);
    }
}
