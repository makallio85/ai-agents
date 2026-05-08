<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\TableRegistry;
use Ramsey\Uuid\Uuid;

class AgentLogService
{
    /**
     * Write a log entry for an agent execution.
     *
     * @param array<string, mixed> $context
     */
    public function log(
        int $agentId,
        string $executionId,
        string $level,
        string $message,
        array $context = [],
        ?int $userId = null,
        ?string $correlationId = null,
        ?int $durationMs = null,
        ?string $resultState = null,
        ?string $errorMessage = null
    ): void {
        /** @var \App\Model\Table\AgentLogsTable $logs */
        $logs = TableRegistry::getTableLocator()->get('AgentLogs');

        $entity = $logs->newEntity([
            'agent_id' => $agentId,
            'execution_id' => $executionId,
            'correlation_id' => $correlationId,
            'user_id' => $userId,
            'level' => $level,
            'message' => $message,
            'context' => !empty($context) ? json_encode($context) : null,
            'duration_ms' => $durationMs,
            'result_state' => $resultState,
            'error_message' => $errorMessage,
        ]);

        $logs->save($entity);
    }

    public function info(int $agentId, string $executionId, string $message, array $context = [], ?int $userId = null): void
    {
        $this->log($agentId, $executionId, 'info', $message, $context, $userId);
    }

    public function error(int $agentId, string $executionId, string $message, string $errorMessage, array $context = [], ?int $userId = null): void
    {
        $this->log($agentId, $executionId, 'error', $message, $context, $userId, null, null, 'failed', $errorMessage);
    }

    public function success(int $agentId, string $executionId, string $message, int $durationMs, array $context = [], ?int $userId = null): void
    {
        $this->log($agentId, $executionId, 'info', $message, $context, $userId, null, $durationMs, 'success');
    }

    public function generateExecutionId(): string
    {
        return Uuid::uuid4()->toString();
    }
}
