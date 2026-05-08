<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class UsersFixture extends TestFixture
{
    public array $records = [
        [
            'id' => 1,
            'role_id' => 1,
            'username' => 'admin',
            'email' => 'admin@example.com',
            // password: admin123
            'password' => '$2y$10$JHYVVHDMEHlVmWc264U8cemZ2ywMHJ5v79wMegW3aKpgtMH.EmOsq',
            'phone' => null,
            'is_active' => 1,
            'mfa_enabled' => 0,
            'last_login_at' => null,
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ],
    ];
}
