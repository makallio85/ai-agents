<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Represents one message turn in a chat session.
 *
 * The role field mirrors the OpenAI / Anthropic convention (user, assistant,
 * system) so that the full message history can be forwarded directly to any
 * supported LLM provider without mapping. tokens_used and model_used are
 * populated only on assistant messages for cost tracking and audit purposes.
 *
 * @property int $id
 * @property int $chat_session_id
 * @property string $role
 * @property string $content
 * @property int|null $tokens_used
 * @property string|null $model_used
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\ChatSession $chat_session
 */
class ChatMessage extends Entity
{
    /** @var array<string, bool> */
    protected array $_accessible = [
        'chat_session_id' => true,
        'role' => true,
        'content' => true,
        'tokens_used' => true,
        'model_used' => true,
        'chat_session' => true,
    ];
}
