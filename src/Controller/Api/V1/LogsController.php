<?php
declare(strict_types=1);

namespace App\Controller\Api\V1;

class LogsController extends AppController
{
    /**
     * GET /api/v1/logs
     * Returns recent agent logs across all agents (admin view).
     */
    public function index(): void
    {
        $this->requirePermission('agent_logs', 'read');

        $query = $this->fetchTable('AgentLogs')->find()->contain(['Agents'])->limit(500)->orderByDesc('AgentLogs.created');

        $data = $this->request->getQueryParams();

        if (!empty($data['agent_id'])) {
            $query->where(['AgentLogs.agent_id' => (int)$data['agent_id']]);
        }
        if (!empty($data['level'])) {
            $query->where(['AgentLogs.level' => $data['level']]);
        }
        if (!empty($data['result_state'])) {
            $query->where(['AgentLogs.result_state' => $data['result_state']]);
        }

        $logs = $query->all()->toList();
        $this->success($logs, ['count' => count($logs)]);
    }
}
