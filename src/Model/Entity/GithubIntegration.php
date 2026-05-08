<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

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
