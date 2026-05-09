<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Persistent mapping from a channel-native user identifier (Slack user_id,
 * etc.) to a platform User row. Populated on first inbound from a known or
 * newly-onboarded sender; consulted on every subsequent inbound to skip the
 * identity-lookup roundtrip.
 *
 * @property int $id
 * @property int $user_id
 * @property string $channel
 * @property string $external_id
 * @property string|null $external_team_id
 * @property string|null $display_name
 * @property string|null $email
 * @property \Cake\I18n\DateTime|null $verified_at
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\User $user
 */
class UserChannelIdentity extends Entity
{
    /** @var array<string, bool> */
    protected array $_accessible = [
        'user_id' => true,
        'channel' => true,
        'external_id' => true,
        'external_team_id' => true,
        'display_name' => true,
        'email' => true,
        'verified_at' => true,
    ];
}
