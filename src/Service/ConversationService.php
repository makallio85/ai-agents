<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Conversation;
use Cake\ORM\TableRegistry;

class ConversationService
{
    public function create(int $userId, int $agentId, string $sourceText, ?string $title = null): Conversation
    {
        $conversations = TableRegistry::getTableLocator()->get('Conversations');

        $entity = $conversations->newEntity([
            'user_id' => $userId,
            'agent_id' => $agentId,
            'title' => $title ?? $this->generateTitle($sourceText),
            'source_text' => $sourceText,
            'status' => Conversation::STATUS_PENDING,
            'blocks_found' => 0,
            'blocks_processed' => 0,
        ]);

        if (!$conversations->save($entity)) {
            throw new \RuntimeException('Failed to create conversation: ' . json_encode($entity->getErrors()));
        }

        return $entity;
    }

    public function updateStatus(Conversation $conversation, string $status): void
    {
        $conversations = TableRegistry::getTableLocator()->get('Conversations');
        $conversation->status = $status;
        $conversations->save($conversation);
    }

    public function findByUser(int $userId): array
    {
        return TableRegistry::getTableLocator()->get('Conversations')
            ->find('byUser', userId: $userId)
            ->contain(['Agents'])
            ->all()
            ->toList();
    }

    public function findById(int $id): ?Conversation
    {
        /** @var Conversation|null */
        return TableRegistry::getTableLocator()->get('Conversations')
            ->find()
            ->where(['Conversations.id' => $id])
            ->contain(['Agents', 'Users'])
            ->first();
    }

    private function generateTitle(string $sourceText): string
    {
        $trimmed = trim(substr($sourceText, 0, 80));
        return strlen($sourceText) > 80 ? $trimmed . '...' : $trimmed;
    }
}
