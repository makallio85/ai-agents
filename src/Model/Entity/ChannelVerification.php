<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * OTP record used to verify a sender's external identifier (e.g. a WhatsApp
 * phone number) before linking it to a User and dispatching the buffered
 * inbound message to an agent handler.
 *
 * @property int $id
 * @property string $channel
 * @property string $external_identifier
 * @property string $code_hash
 * @property \Cake\I18n\DateTime $expires_at
 * @property int $attempts
 * @property bool $verified
 * @property \Cake\I18n\DateTime|null $verified_at
 * @property int|null $agent_id
 * @property string|null $pending_payload
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class ChannelVerification extends Entity
{
    /** @var array<string, bool> */
    protected array $_accessible = [
        'channel' => true,
        'external_identifier' => true,
        'code_hash' => true,
        'expires_at' => true,
        'attempts' => true,
        'verified' => true,
        'verified_at' => true,
        'agent_id' => true,
        'pending_payload' => true,
    ];
}
