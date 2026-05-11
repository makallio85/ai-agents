<?php
declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * Seeds the initial administrator account.
 *
 * Run once on a fresh install after InitialDataSeed has been executed.
 * Skips insertion if an admin user already exists to make it safe to re-run.
 *
 * Default credentials — change the password immediately after first login:
 *   Email:    admin@ai-agents.local
 *   Password: Admin123!
 */
class AdminUserSeed extends BaseSeed
{
    public function run(): void
    {
        $adapter = $this->getAdapter();
        $existing = $adapter->fetchAll("SELECT id FROM users WHERE email = 'admin@ai-agents.local'");

        if (count($existing) > 0) {
            echo "Admin user already exists, skipping.\n";
            return;
        }

        $adminRoleId = $adapter->fetchAll("SELECT id FROM roles WHERE slug = 'administrator'");
        if (empty($adminRoleId)) {
            throw new RuntimeException('Administrator role not found. Run InitialDataSeed first.');
        }

        $now = date('Y-m-d H:i:s');

        $this->table('users')->insert([[
            'role_id'        => (int)$adminRoleId[0]['id'],
            'username'       => 'admin',
            'email'          => 'admin@ai-agents.local',
            'password'       => '$2y$10$YZdXK/gfeCzdaI2JF3d9hOn7nc.cAOlMgEjbUi.e4OHh4/Mxp0rd2',
            'first_name'     => 'Admin',
            'last_name'      => 'User',
            'is_active'      => true,
            'is_approved'    => true,
            'approval_state' => 'approved',
            'mfa_enabled'    => false,
            'created'        => $now,
            'modified'       => $now,
        ]])->saveData();

        echo "Admin user created: admin@ai-agents.local / Admin123!\n";
    }
}
