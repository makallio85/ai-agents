<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property int $user_id
 * @property string $repo_owner
 * @property string $repo_name
 * @property string $token
 * @property bool $is_active
 * @property \Cake\I18n\DateTime|null $last_used_at
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\User $user
 */
class GithubIntegration extends Entity
{
    protected array $_accessible = [
        'user_id' => true,
        'repo_owner' => true,
        'repo_name' => true,
        'token' => true,
        'is_active' => true,
        'last_used_at' => true,
    ];

    protected array $_hidden = ['token'];
}
