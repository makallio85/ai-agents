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
 * Channel/direction/status/external_message_id fields support multi-channel
 * delivery; sender_user_id distinguishes a human-typed reply (handoff) from
 * an LLM-generated one without inventing a new role.
 *
 * @property int $id
 * @property int $chat_session_id
 * @property string $role
 * @property string $channel
 * @property string $direction
 * @property int|null $sender_user_id
 * @property string $content
 * @property string $content_type
 * @property string|null $media_url
 * @property string|null $media_mime_type
 * @property string|null $external_message_id
 * @property string|null $external_thread_id
 * @property string $status
 * @property string|null $error_code
 * @property string|null $error_message
 * @property string|null $metadata
 * @property \Cake\I18n\DateTime|null $sent_at
 * @property \Cake\I18n\DateTime|null $delivered_at
 * @property \Cake\I18n\DateTime|null $read_at
 * @property int|null $tokens_used
 * @property string|null $model_used
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\ChatSession $chat_session
 * @property \App\Model\Entity\User|null $sender_user
 */
class ChatMessage extends Entity
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_SYSTEM = 'system';

    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';

    public const STATUS_RECEIVED = 'received';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';

    public const CONTENT_TEXT = 'text';
    public const CONTENT_IMAGE = 'image';
    public const CONTENT_AUDIO = 'audio';
    public const CONTENT_DOCUMENT = 'document';
    public const CONTENT_TEMPLATE = 'template';

    /** @var array<string, bool> */
    protected array $_accessible = [
        'chat_session_id' => true,
        'role' => true,
        'channel' => true,
        'direction' => true,
        'sender_user_id' => true,
        'content' => true,
        'content_type' => true,
        'media_url' => true,
        'media_mime_type' => true,
        'external_message_id' => true,
        'external_thread_id' => true,
        'status' => true,
        'error_code' => true,
        'error_message' => true,
        'metadata' => true,
        'sent_at' => true,
        'delivered_at' => true,
        'read_at' => true,
        'tokens_used' => true,
        'model_used' => true,
        'chat_session' => true,
        'sender_user' => true,
    ];

    /**
     * True when the outbound message was typed by a human (handoff) rather than the LLM.
     */
    public function isFromHuman(): bool
    {
        return $this->sender_user_id !== null;
    }
}
