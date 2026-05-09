<?php
declare(strict_types=1);

namespace App\Service;

use App\Integration\Llm\LlmMessage;
use App\Model\Entity\ChatMessage;
use App\Model\Entity\ChatSession;
use Cake\ORM\TableRegistry;
use RuntimeException;

/**
 * Manages chat sessions and their message history.
 *
 * Handles all CRUD operations for chat_sessions and chat_messages and
 * provides the helper that assembles the full ordered message array required
 * by the LLM clients. Called by ChatController (API) to persist state before
 * and after each streaming turn.
 */
class ChatSessionService
{
    /**
     * Returns all sessions for a given user, newest first, with agent eager-loaded.
     *
     * @return ChatSession[]
     */
    public function findByUser(int $userId): array
    {
        /** @var ChatSession[] */
        return TableRegistry::getTableLocator()->get('ChatSessions')
            ->find('byUser', userId: $userId)
            ->all()
            ->toList();
    }

    /**
     * Returns a single session with its full message history, or null if not found.
     *
     * Ownership check is the caller's responsibility; this method does not
     * enforce access control.
     */
    public function findById(int $id): ?ChatSession
    {
        /** @var ChatSession|null */
        return TableRegistry::getTableLocator()->get('ChatSessions')
            ->find()
            ->contain(['Agents', 'ChatMessages'])
            ->where(['ChatSessions.id' => $id])
            ->first();
    }

    /**
     * Creates a new chat session for the given user and agent.
     *
     * The title defaults to null; the frontend may update it after the first
     * message to something more descriptive.
     *
     * @throws RuntimeException If persistence fails.
     */
    public function create(int $userId, int $agentId, ?string $title = null): ChatSession
    {
        $table = TableRegistry::getTableLocator()->get('ChatSessions');
        $entity = $table->newEntity([
            'user_id' => $userId,
            'agent_id' => $agentId,
            'title' => $title,
        ]);

        if (!$table->save($entity)) {
            throw new RuntimeException('Failed to create chat session: ' . json_encode($entity->getErrors()));
        }

        /** @var ChatSession $entity */
        return $entity;
    }

    /**
     * Deletes a session and all its messages (cascade handled by DB).
     *
     * @throws RuntimeException If deletion fails.
     */
    public function delete(ChatSession $session): void
    {
        $table = TableRegistry::getTableLocator()->get('ChatSessions');
        if (!$table->delete($session)) {
            throw new RuntimeException("Failed to delete chat session {$session->id}");
        }
    }

    /**
     * Persists a single message turn to the database.
     *
     * Used both to record the user's incoming message before calling the LLM
     * and to store the completed assistant response after streaming finishes.
     *
     * @throws RuntimeException If persistence fails.
     */
    public function addMessage(
        int $sessionId,
        string $role,
        string $content,
        ?int $tokensUsed = null,
        ?string $modelUsed = null,
    ): ChatMessage {
        $table = TableRegistry::getTableLocator()->get('ChatMessages');
        $entity = $table->newEntity([
            'chat_session_id' => $sessionId,
            'role' => $role,
            'content' => $content,
            'tokens_used' => $tokensUsed,
            'model_used' => $modelUsed,
        ]);

        if (!$table->save($entity)) {
            throw new RuntimeException('Failed to save chat message: ' . json_encode($entity->getErrors()));
        }

        /** @var ChatMessage $entity */
        return $entity;
    }

    /**
     * Assembles the full ordered message history for a session as LlmMessage DTOs.
     *
     * Returns messages in chronological order (oldest first) as required by
     * all supported LLM providers. The caller (LlmService) prepends the
     * system prompt before passing this list to the client.
     *
     * @param ChatSession $session Must already have chat_messages eagerly loaded.
     * @return LlmMessage[]
     */
    public function buildMessageHistory(ChatSession $session): array
    {
        $messages = [];
        foreach ($session->chat_messages as $msg) {
            $messages[] = new LlmMessage($msg->role, $msg->content);
        }
        return $messages;
    }

    /**
     * Updates the title of a session (e.g. derived from the first user message).
     *
     * @throws RuntimeException If save fails.
     */
    public function updateTitle(ChatSession $session, string $title): void
    {
        $table = TableRegistry::getTableLocator()->get('ChatSessions');
        $session->title = $title;
        if (!$table->save($session)) {
            throw new RuntimeException("Failed to update chat session title for session {$session->id}");
        }
    }
}
