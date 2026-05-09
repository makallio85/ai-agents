<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Adds users.preferred_reply_mode controlling whether agents reply to the
 * user as text or as audio (TTS).
 *
 * Values:
 *   - 'auto'  : mirror the user's last inbound message — audio in -> audio
 *               out, text in -> text out. Default for new and existing rows.
 *   - 'text'  : always reply as text, regardless of how the user wrote in.
 *   - 'audio' : always reply as audio (synthesised via TTS) when the
 *               channel supports outbound audio; falls back to text when
 *               it does not.
 *
 * The MessageDispatcher reads this column when persisting an outbound
 * chat_messages row and chooses the appropriate content_type.
 */
class AddPreferredReplyModeToUsers extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('users');
        $table->addColumn('preferred_reply_mode', 'string', [
                  'limit' => 10,
                  'null' => false,
                  'default' => 'auto',
                  'comment' => 'auto|text|audio',
                  'after' => 'approved_at',
              ])
              ->update();
    }
}
