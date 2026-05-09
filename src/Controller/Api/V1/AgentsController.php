<?php
declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Channels\Slack\Service\SlackConfigService;
use App\Channels\WhatsApp\Service\WhatsAppConfigService;
use App\Service\AgentService;
use App\Service\AgentLogService;

class AgentsController extends AppController
{
    private AgentService $agentService;
    private WhatsAppConfigService $whatsappConfig;
    private SlackConfigService $slackConfig;

    public function initialize(): void
    {
        parent::initialize();
        $this->agentService = new AgentService(new AgentLogService());
        $this->whatsappConfig = new WhatsAppConfigService();
        $this->slackConfig = new SlackConfigService();
    }

    /**
     * GET /api/v1/agents
     */
    public function index(): void
    {
        $this->requirePermission('agents', 'read');
        $agents = $this->agentService->findAll();
        $this->success($agents, ['count' => count($agents)]);
    }

    /**
     * GET /api/v1/agents/view/:id
     */
    public function view(int $id): void
    {
        $this->requirePermission('agents', 'read');
        $agent = $this->agentService->findById($id);

        if ($agent === null) {
            $this->error('Agent not found', [], 404);
            return;
        }

        $this->success($agent);
    }

    /**
     * POST /api/v1/agents/create
     */
    public function create(): void
    {
        $this->requirePermission('agents', 'create');
        $data = $this->request->getData();

        try {
            $agent = $this->agentService->create($data);
            $this->success($agent, [], 201);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage(), [], 422);
        }
    }

    /**
     * PUT /api/v1/agents/update/:id
     */
    public function update(int $id): void
    {
        $this->requirePermission('agents', 'update');
        $agent = $this->agentService->findById($id);

        if ($agent === null) {
            $this->error('Agent not found', [], 404);
            return;
        }

        try {
            $updated = $this->agentService->update($agent, $this->request->getData());
            $this->success($updated);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage(), [], 422);
        }
    }

    /**
     * DELETE /api/v1/agents/delete/:id
     */
    public function delete(int $id): void
    {
        $this->requirePermission('agents', 'delete');
        $agent = $this->agentService->findById($id);

        if ($agent === null) {
            $this->error('Agent not found', [], 404);
            return;
        }

        try {
            $this->agentService->delete($agent);
            $this->success(null, [], 204);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage(), [], 422);
        }
    }

    /**
     * GET /api/v1/agents/logs/:id
     */
    public function logs(int $id): void
    {
        $this->requirePermission('agents', 'read');
        $agent = $this->agentService->findById($id);

        if ($agent === null) {
            $this->error('Agent not found', [], 404);
            return;
        }

        $logs = $this->fetchTable('AgentLogs')
            ->find('byAgent', agentId: $id)
            ->limit(200)
            ->all()
            ->toList();

        $this->success($logs, ['count' => count($logs)]);
    }

    /**
     * GET /api/v1/agents/whatsapp-config/:id
     *
     * Returns the agent's WhatsApp settings for the admin UI. Sensitive
     * fields (access token) are not echoed back; the UI gets a boolean
     * "access_token_set" flag instead so admins can see whether a value
     * already exists without exposing it.
     */
    public function whatsappConfig(int $id): void
    {
        $this->requirePermission('chat', 'configure');
        $agent = $this->agentService->findById($id);
        if ($agent === null) {
            $this->error('Agent not found', [], 404);
            return;
        }
        $this->success($this->whatsappConfig->readForUi($id));
    }

    /**
     * POST /api/v1/agents/whatsapp-config/:id
     *
     * Body: { phone_number_id, display_number, access_token?, welcome_template_name?, enabled }
     *
     * access_token is optional on update — leaving it blank means "keep what
     * is already stored" so the admin doesn't have to paste the long token
     * back in on every edit. Encryption happens in WhatsAppConfigService.
     */
    public function updateWhatsappConfig(int $id): void
    {
        $this->requirePermission('chat', 'configure');
        $agent = $this->agentService->findById($id);
        if ($agent === null) {
            $this->error('Agent not found', [], 404);
            return;
        }
        $data = $this->request->getData();
        $phoneNumberId = trim((string)($data['phone_number_id'] ?? ''));
        $displayNumber = trim((string)($data['display_number'] ?? ''));
        if ($phoneNumberId === '' || $displayNumber === '') {
            $this->error('phone_number_id and display_number are required', [], 422);
            return;
        }

        $accessToken = isset($data['access_token']) ? trim((string)$data['access_token']) : null;
        $template = isset($data['welcome_template_name']) ? (string)$data['welcome_template_name'] : null;
        $enabled = (bool)($data['enabled'] ?? false);

        $this->whatsappConfig->setForAgent(
            agentId: $id,
            phoneNumberId: $phoneNumberId,
            displayNumber: $displayNumber,
            accessToken: $accessToken === '' ? null : $accessToken,
            welcomeTemplateName: $template === '' ? null : $template,
            enabled: $enabled,
        );

        $this->success($this->whatsappConfig->readForUi($id));
    }

    /**
     * GET /api/v1/agents/slack-config/:id
     */
    public function slackConfig(int $id): void
    {
        $this->requirePermission('chat', 'configure');
        $agent = $this->agentService->findById($id);
        if ($agent === null) {
            $this->error('Agent not found', [], 404);
            return;
        }
        $this->success($this->slackConfig->readForUi($id));
    }

    /**
     * POST /api/v1/agents/slack-config/:id
     *
     * Body: { app_id, bot_user_id, bot_token?, signing_secret?, team_id?, enabled }
     *
     * bot_token and signing_secret are optional on update — leave blank to
     * keep existing encrypted values. Both are mandatory on first save.
     */
    public function updateSlackConfig(int $id): void
    {
        $this->requirePermission('chat', 'configure');
        $agent = $this->agentService->findById($id);
        if ($agent === null) {
            $this->error('Agent not found', [], 404);
            return;
        }

        $data = $this->request->getData();
        $appId = trim((string)($data['app_id'] ?? ''));
        $botUserId = trim((string)($data['bot_user_id'] ?? ''));
        if ($appId === '' || $botUserId === '') {
            $this->error('app_id and bot_user_id are required', [], 422);
            return;
        }

        $current = $this->slackConfig->readForUi($id);
        $botToken = isset($data['bot_token']) ? trim((string)$data['bot_token']) : null;
        $signingSecret = isset($data['signing_secret']) ? trim((string)$data['signing_secret']) : null;
        if (!$current['bot_token_set'] && ($botToken === null || $botToken === '')) {
            $this->error('bot_token is required on first save', [], 422);
            return;
        }
        if (!$current['signing_secret_set'] && ($signingSecret === null || $signingSecret === '')) {
            $this->error('signing_secret is required on first save', [], 422);
            return;
        }

        $teamId = isset($data['team_id']) ? trim((string)$data['team_id']) : null;
        $enabled = (bool)($data['enabled'] ?? false);

        $this->slackConfig->setForAgent(
            agentId: $id,
            appId: $appId,
            botUserId: $botUserId,
            botToken: $botToken === '' ? null : $botToken,
            signingSecret: $signingSecret === '' ? null : $signingSecret,
            teamId: $teamId === '' ? null : $teamId,
            enabled: $enabled,
        );

        $this->success($this->slackConfig->readForUi($id));
    }
}
