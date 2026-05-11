<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\Auth\DefaultPasswordHasher;
use Cake\ORM\Entity;

/**
 * @property int $id
 * @property int $role_id
 * @property string $username
 * @property string $email
 * @property string $password
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $phone_number
 * @property bool $mfa_enabled
 * @property string|null $mfa_secret
 * @property bool $is_active
 * @property bool $is_approved
 * @property string $approval_state
 * @property int|null $approved_by_user_id
 * @property \Cake\I18n\DateTime|null $approved_at
 * @property string $preferred_reply_mode
 * @property \Cake\I18n\DateTime|null $last_login_at
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class User extends Entity
{
    public const APPROVAL_PENDING = 'pending';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';

    public const REPLY_AUTO = 'auto';
    public const REPLY_TEXT = 'text';
    public const REPLY_AUDIO = 'audio';

    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'role_id' => true,
        'username' => true,
        'email' => true,
        'password' => true,
        'first_name' => true,
        'last_name' => true,
        'phone_number' => true,
        'mfa_enabled' => true,
        'mfa_secret' => true,
        'is_active' => true,
        'is_approved' => true,
        'approval_state' => true,
        'approved_by_user_id' => true,
        'approved_at' => true,
        'preferred_reply_mode' => true,
        'last_login_at' => true,
    ];

    /**
     * @var array<string>
     */
    protected array $_hidden = ['password', 'mfa_secret'];

    protected function _setPassword(string $password): string
    {
        if (strlen($password) > 0) {
            return (new DefaultPasswordHasher())->hash($password);
        }
        return $password;
    }

    public function getFullName(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }
}
