<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Job;

use App\Model\Entity\Conversation;
use App\Service\AgentLogService;
use App\Service\ConversationService;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use DevOpsOrchestrator\Service\IssueParserService;
use DevOpsOrchestrator\Service\IssueValidatorService;
use Interop\Queue\Processor;

/**
 * Parses all issue blocks from a conversation and dispatches CreateGitHubIssueJob per valid block.
 */
class ParseIssueJob implements JobInterface
{
    public static int $maxAttempts = 3;
    public static bool $shouldBeUnique = false;

    public function __construct(
        private readonly IssueParserService $parserService,
        private readonly IssueValidatorService $validatorService,
        private readonly ConversationService $conversationService,
        private readonly AgentLogService $logService
    ) {
    }

    public function execute(Message $message): ?string
    {
        $payload = $message->getArgument('payload', []);

        $conversationId = (int)($payload['conversation_id'] ?? 0);
        $agentId = (int)($payload['agent_id'] ?? 0);
        $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : null;
        $executionId = (string)($payload['execution_id'] ?? '');
        $githubOwner = (string)($payload['github_owner'] ?? '');
        $githubRepo = (string)($payload['github_repo'] ?? '');
        $githubToken = (string)($payload['github_token'] ?? '');

        if (!$conversationId || !$agentId || !$executionId) {
            return Processor::REJECT;
        }

        $conversation = $this->conversationService->findById($conversationId);

        if ($conversation === null) {
            return Processor::REJECT;
        }

        $this->conversationService->updateStatus($conversation, Conversation::STATUS_PROCESSING);
        $this->logService->info($agentId, $executionId, "Parsing conversation #{$conversationId}", [], $userId);

        $parsedIssues = $this->parserService->parseAll($conversation->source_text);
        $blocksFound = count($parsedIssues);

        $conversations = \Cake\ORM\TableRegistry::getTableLocator()->get('Conversations');
        $conversation->blocks_found = $blocksFound;
        $conversations->save($conversation);

        if ($blocksFound === 0) {
            $this->logService->info($agentId, $executionId, 'No issue blocks found in conversation', [], $userId);
            $this->conversationService->updateStatus($conversation, Conversation::STATUS_COMPLETED);
            return Processor::ACK;
        }

        $this->logService->info($agentId, $executionId, "Found {$blocksFound} issue block(s)", [], $userId);

        $issueParsingJobs = \Cake\ORM\TableRegistry::getTableLocator()->get('IssueParsingJobs');

        foreach ($parsedIssues as $index => $dto) {
            $validationErrors = $this->validatorService->validate($dto);

            $jobEntity = $issueParsingJobs->newEntity([
                'conversation_id' => $conversationId,
                'agent_id' => $agentId,
                'execution_id' => $executionId,
                'raw_block' => $dto->rawBlock,
                'parsed_data' => json_encode([
                    'title' => $dto->title,
                    'body' => $dto->body,
                    'issue_type' => $dto->issueType,
                ]),
                'status' => empty($validationErrors) ? 'pending' : 'failed',
                'error_message' => empty($validationErrors) ? null : implode('; ', $validationErrors),
            ]);

            $issueParsingJobs->save($jobEntity);

            if (!empty($validationErrors)) {
                $this->logService->error(
                    $agentId,
                    $executionId,
                    "Block #{$index} validation failed",
                    implode('; ', $validationErrors),
                    [],
                    $userId
                );
                continue;
            }

            // Dispatch CreateGitHubIssueJob for each valid block
            \Cake\Queue\QueueManager::push(
                CreateGitHubIssueJob::class,
                [
                    'payload' => [
                        'issue_parsing_job_id' => $jobEntity->id,
                        'conversation_id' => $conversationId,
                        'agent_id' => $agentId,
                        'user_id' => $userId,
                        'execution_id' => $executionId,
                        'github_owner' => $githubOwner,
                        'github_repo' => $githubRepo,
                        'github_token' => $githubToken,
                        'parsed_data' => [
                            'title' => $dto->title,
                            'body' => $dto->body,
                            'issue_type' => $dto->issueType,
                        ],
                    ],
                ]
            );
        }

        return Processor::ACK;
    }
}
