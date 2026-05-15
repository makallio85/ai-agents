<?php
declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Service\AgentService;
use App\Service\AgentLogService;

class AgentsController extends AppController
{
    private AgentService $agentService;

    public function initialize(): void
    {
        parent::initialize();
        $this->agentService = new AgentService(new AgentLogService());
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

}
