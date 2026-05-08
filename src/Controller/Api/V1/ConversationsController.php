<?php
declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Model\Entity\Conversation;
use App\Service\AgentLogService;
use App\Service\ConversationService;
use Cake\Queue\QueueManager;
use DevOpsOrchestrator\Job\ParseIssueJob;
use Ramsey\Uuid\Uuid;

class ConversationsController extends AppController
{
    private ConversationService $conversationService;

    public function initialize(): void
    {
        parent::initialize();
        $this->conversationService = new ConversationService();
    }

    /**
     * GET /api/v1/conversations
     */
    public function index(): void
    {
        $this->requirePermission('conversations', 'read');
        $user = $this->getCurrentUser();
        $conversations = $this->conversationService->findByUser($user->id);
        $this->success($conversations, ['count' => count($conversations)]);
    }

    /**
     * GET /api/v1/conversations/view/:id
     */
    public function view(int $id): void
    {
        $this->requirePermission('conversations', 'read');
        $conversation = $this->conversationService->findById($id);

        if ($conversation === null) {
            $this->error('Conversation not found', [], 404);
            return;
        }

        // Load issue parsing jobs for this conversation
        $jobs = $this->fetchTable('IssueParsingJobs')
            ->find('byConversation', conversationId: $id)
            ->all()
            ->toList();

        $this->success([
            'conversation' => $conversation,
            'jobs' => $jobs,
        ]);
    }

    /**
     * POST /api/v1/conversations/create
     * Accepts { agent_id, source_text, title?, github_integration_id }
     */
    public function create(): void
    {
        $this->requirePermission('conversations', 'create');
        $user = $this->getCurrentUser();
        $data = $this->request->getData();

        $agentId = (int)($data['agent_id'] ?? 0);
        $sourceText = (string)($data['source_text'] ?? '');
        $githubIntegrationId = (int)($data['github_integration_id'] ?? 0);

        if (!$agentId || empty($sourceText)) {
            $this->error('agent_id and source_text are required', [], 422);
            return;
        }

        if (!$githubIntegrationId) {
            $this->error('github_integration_id is required', [], 422);
            return;
        }

        /** @var \App\Model\Entity\GithubIntegration|null $integration */
        $integration = $this->fetchTable('GithubIntegrations')
            ->find()
            ->where(['GithubIntegrations.id' => $githubIntegrationId, 'GithubIntegrations.user_id' => $user->id])
            ->first();

        if ($integration === null) {
            $this->error('GitHub integration not found', [], 404);
            return;
        }

        try {
            $conversation = $this->conversationService->create(
                userId: $user->id,
                agentId: $agentId,
                sourceText: $sourceText,
                title: $data['title'] ?? null
            );

            $executionId = Uuid::uuid4()->toString();

            // Dispatch parse job to the queue
            QueueManager::push(ParseIssueJob::class, [
                'payload' => [
                    'conversation_id' => $conversation->id,
                    'agent_id' => $agentId,
                    'user_id' => $user->id,
                    'execution_id' => $executionId,
                    'github_owner' => $integration->repo_owner,
                    'github_repo' => $integration->repo_name,
                    'github_token' => $integration->token,
                ],
            ]);

            $this->success($conversation, [], 201);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage(), [], 422);
        }
    }

    /**
     * DELETE /api/v1/conversations/delete/:id
     */
    public function delete(int $id): void
    {
        $this->requirePermission('conversations', 'delete');
        $conversation = $this->conversationService->findById($id);

        if ($conversation === null) {
            $this->error('Conversation not found', [], 404);
            return;
        }

        $this->fetchTable('Conversations')->delete($conversation);
        $this->success(null, [], 204);
    }
}
