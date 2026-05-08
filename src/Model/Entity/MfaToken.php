<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\I18n\DateTime;
use Cake\ORM\Entity;

class MfaToken extends Entity
{
    protected array $_accessible = [
        'user_id' => true,
        'token_hash' => true,
        'expires_at' => true,
        'used' => true,
    ];

    protected array $_hidden = ['token_hash'];

    public function isExpired(): bool
    {
        return $this->expires_at < new DateTime();
    }
}
