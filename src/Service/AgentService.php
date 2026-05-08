<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Agent;
use Cake\ORM\TableRegistry;

class AgentService
{
    public function __construct(
        private readonly AgentLogService $logService
    ) {
    }

    public function findAll(): array
    {
        return TableRegistry::getTableLocator()->get('Agents')->find()->all()->toList();
    }

    public function findEnabled(): array
    {
        return TableRegistry::getTableLocator()->get('Agents')->find('enabled')->all()->toList();
    }

    public function findById(int $id): ?Agent
    {
        /** @var Agent|null */
        return TableRegistry::getTableLocator()->get('Agents')->find()
            ->where(['Agents.id' => $id])
            ->contain(['AgentContexts', 'PromptVersions'])
            ->first();
    }

    public function create(array $data): Agent
    {
        $agents = TableRegistry::getTableLocator()->get('Agents');
        $entity = $agents->newEntity($data);

        if (!$agents->save($entity)) {
            throw new \RuntimeException('Failed to create agent: ' . json_encode($entity->getErrors()));
        }

        return $entity;
    }

    public function update(Agent $agent, array $data): Agent
    {
        $agents = TableRegistry::getTableLocator()->get('Agents');
        $agents->patchEntity($agent, $data);

        if (!$agents->save($agent)) {
            throw new \RuntimeException('Failed to update agent: ' . json_encode($agent->getErrors()));
        }

        return $agent;
    }

    public function delete(Agent $agent): void
    {
        $agents = TableRegistry::getTableLocator()->get('Agents');
        if (!$agents->delete($agent)) {
            throw new \RuntimeException("Failed to delete agent {$agent->id}");
        }
    }
}
