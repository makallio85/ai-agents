<?php
declare(strict_types=1);

use App\Database\RolePermissionMatrix;
use Migrations\BaseSeed;

/**
 * InitialData seed.
 *
 * Idempotent — re-running the seed only inserts rows that are missing.
 * Reads its permission set from {@see RolePermissionMatrix} so the seed,
 * the BackfillRolePermissions migration and the PermissionsFixture all
 * share a single source of truth.
 */
class InitialDataSeed extends BaseSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/migrations/5/en/seeding.html
     *
     * @return void
     */
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $adapter = $this->getAdapter();

        // Roles — skip if already seeded
        $existingRoles = $adapter->fetchAll("SELECT slug FROM roles WHERE slug IN ('administrator', 'superuser', 'user')");
        $existingSlugs = array_column($existingRoles, 'slug');

        $rolesToInsert = [];
        foreach ([
            ['name' => 'Administrator', 'slug' => 'administrator', 'description' => 'Full infrastructure and system access'],
            ['name' => 'Superuser',     'slug' => 'superuser',     'description' => 'Full operational access to all agents and workflows'],
            ['name' => 'User',          'slug' => 'user',          'description' => 'Limited operational access'],
        ] as $role) {
            if (!in_array($role['slug'], $existingSlugs, true)) {
                $rolesToInsert[] = array_merge($role, ['created' => $now, 'modified' => $now]);
            }
        }

        if (!empty($rolesToInsert)) {
            $this->table('roles')->insert($rolesToInsert)->saveData();
        }

        // Look up actual IDs by slug (never assume auto-increment values)
        $rows = $adapter->fetchAll("SELECT id, slug FROM roles WHERE slug IN ('administrator', 'superuser', 'user')");
        $roleIds = [];
        foreach ($rows as $row) {
            $roleIds[$row['slug']] = (int)$row['id'];
        }

        // Permissions — pull the canonical matrix and insert only what's missing.
        if (!empty($roleIds)) {
            $existingPerms = $adapter->fetchAll(
                'SELECT role_id, module, action FROM permissions WHERE role_id IN ('
                . implode(',', array_values($roleIds)) . ')'
            );
            $permSet = [];
            foreach ($existingPerms as $p) {
                $permSet[$p['role_id'] . '|' . $p['module'] . '|' . $p['action']] = true;
            }

            $permissionsData = [];
            foreach (RolePermissionMatrix::rows($roleIds) as $row) {
                $key = $row['role_id'] . '|' . $row['module'] . '|' . $row['action'];
                if (!isset($permSet[$key])) {
                    $permissionsData[] = [
                        'role_id' => $row['role_id'],
                        'module' => $row['module'],
                        'action' => $row['action'],
                        'created' => $now,
                        'modified' => $now,
                    ];
                }
            }

            if (!empty($permissionsData)) {
                $this->table('permissions')->insert($permissionsData)->saveData();
            }
        }

        // Labels — skip if already present
        $existingLabels = $adapter->fetchAll("SELECT slug FROM labels WHERE slug IN ('bug', 'enhancement')");
        $existingLabelSlugs = array_column($existingLabels, 'slug');

        $labelsToInsert = [];
        if (!in_array('bug', $existingLabelSlugs, true)) {
            $labelsToInsert[] = [
                'name' => 'Bug', 'slug' => 'bug', 'color' => '#d73a4a',
                'description' => 'Something is not working',
                'keywords' => json_encode(['bug', 'error', 'fix', 'broken', 'crash', 'issue', 'fail', 'not working']),
                'created' => $now, 'modified' => $now,
            ];
        }
        if (!in_array('enhancement', $existingLabelSlugs, true)) {
            $labelsToInsert[] = [
                'name' => 'Enhancement', 'slug' => 'enhancement', 'color' => '#a2eeef',
                'description' => 'New feature or request',
                'keywords' => json_encode(['feature', 'enhancement', 'improve', 'add', 'new', 'request', 'implement', 'support']),
                'created' => $now, 'modified' => $now,
            ];
        }

        if (!empty($labelsToInsert)) {
            $this->table('labels')->insert($labelsToInsert)->saveData();
        }

        // DevOps Orchestrator Agent — skip if already present
        $existingAgent = $adapter->fetchAll("SELECT id FROM agents WHERE slug = 'devops-orchestrator'");
        if (empty($existingAgent)) {
            $this->table('agents')->insert([[
                'name' => 'DevOps Orchestrator',
                'slug' => 'devops-orchestrator',
                'plugin' => 'DevOpsOrchestrator',
                'description' => 'Parses issue specifications from OpenAI/ChatGPT conversations and creates GitHub Issues automatically.',
                'is_enabled' => true,
                'llm_provider' => null,
                'llm_model' => null,
                'instructions' => 'Parse issue specifications from conversations. Validate format. Detect issue type. Create GitHub issues with correct labels.',
                'config' => null,
                'created' => $now,
                'modified' => $now,
            ]])->saveData();
        }
    }
}
