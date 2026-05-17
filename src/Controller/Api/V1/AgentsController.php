<?php
declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Service\AgentIntegrationPermissionService;
use App\Service\AgentLogService;
use App\Service\AgentService;

class AgentsController extends AppController
{
    private AgentService $agentService;
    private AgentLogService $logService;
    private AgentIntegrationPermissionService $permissionService;

    public function initialize(): void
    {
        parent::initialize();
        $this->logService = new AgentLogService();
        $this->agentService = new AgentService($this->logService);
        $this->permissionService = new AgentIntegrationPermissionService();
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
     * GET /api/v1/agents/permissions/:id
     *
     * Returns the full integration-permission catalog plus the set of action
     * keys currently granted to this agent. The frontend renders the catalog
     * as a checklist with each item pre-checked if its key is in `granted`.
     *
     * Response shape:
     *   {
     *     "catalog": { "github": [ {"action": "...", "label": "..."}, ... ] },
     *     "granted": ["github.issues.read", ...]
     *   }
     */
    public function permissions(int $id): void
    {
        $this->requirePermission('agents', 'read');
        $agent = $this->agentService->findById($id);

        if ($agent === null) {
            $this->error('Agent not found', [], 404);
            return;
        }

        $this->success([
            'catalog' => $this->permissionService->getCatalog(),
            'granted' => $this->permissionService->loadForAgent($id)->all(),
        ]);
    }

    /**
     * POST /api/v1/agents/update-permissions/:id
     *
     * Body: { actions: ["github.issues.read", ...] }
     *
     * Replaces the agent's permission grants with the supplied set of action
     * keys. Unknown action keys are silently dropped by the service, so the
     * stored set always matches the canonical catalog. Writes an audit row to
     * agent_logs so operators can see who changed the grants and when.
     */
    public function updatePermissions(int $id): void
    {
        $this->requirePermission('agents', 'update');
        $agent = $this->agentService->findById($id);

        if ($agent === null) {
            $this->error('Agent not found', [], 404);
            return;
        }

        $rawActions = $this->request->getData('actions');
        if (!is_array($rawActions)) {
            $this->error('actions must be an array of action keys', [], 422);
            return;
        }

        $actions = [];
        foreach ($rawActions as $action) {
            if (is_string($action) && $action !== '') {
                $actions[] = $action;
            }
        }

        $this->permissionService->replaceForAgent($id, $actions);

        $granted = $this->permissionService->loadForAgent($id)->all();
        $user = $this->getCurrentUser();
        $this->logService->log(
            agentId: $id,
            executionId: 'permissions-update',
            level: 'info',
            message: sprintf('Integration permissions updated (%d grant(s)).', count($granted)),
            context: ['granted' => $granted],
            userId: $user?->id,
            resultState: 'success',
        );

        $this->success([
            'catalog' => $this->permissionService->getCatalog(),
            'granted' => $granted,
        ]);
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
