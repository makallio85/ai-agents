<?php
declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * InitialData seed.
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

        // Roles
        $rolesTable = $this->table('roles');
        $rolesTable->insert([
            ['name' => 'Administrator', 'slug' => 'administrator', 'description' => 'Full infrastructure and system access', 'created' => $now, 'modified' => $now],
            ['name' => 'Superuser', 'slug' => 'superuser', 'description' => 'Full operational access to all agents and workflows', 'created' => $now, 'modified' => $now],
            ['name' => 'User', 'slug' => 'user', 'description' => 'Limited operational access', 'created' => $now, 'modified' => $now],
        ])->save();

        // Permissions for Administrator (role_id=1)
        $modules = ['agents', 'conversations', 'users', 'roles', 'labels', 'github_integrations', 'execution_history', 'agent_logs', 'prompt_versions'];
        $actions = ['read', 'create', 'update', 'delete'];
        $permissionsData = [];
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $permissionsData[] = ['role_id' => 1, 'module' => $module, 'action' => $action, 'created' => $now, 'modified' => $now];
                $permissionsData[] = ['role_id' => 2, 'module' => $module, 'action' => $action, 'created' => $now, 'modified' => $now];
            }
        }
        // User role — read only on agents and conversations
        $permissionsData[] = ['role_id' => 3, 'module' => 'agents', 'action' => 'read', 'created' => $now, 'modified' => $now];
        $permissionsData[] = ['role_id' => 3, 'module' => 'conversations', 'action' => 'read', 'created' => $now, 'modified' => $now];
        $permissionsData[] = ['role_id' => 3, 'module' => 'conversations', 'action' => 'create', 'created' => $now, 'modified' => $now];

        $permissionsTable = $this->table('permissions');
        $permissionsTable->insert($permissionsData)->save();

        // Labels
        $labelsTable = $this->table('labels');
        $labelsTable->insert([
            [
                'name' => 'Bug',
                'slug' => 'bug',
                'color' => '#d73a4a',
                'description' => 'Something is not working',
                'keywords' => json_encode(['bug', 'error', 'fix', 'broken', 'crash', 'issue', 'fail', 'not working']),
                'created' => $now,
                'modified' => $now,
            ],
            [
                'name' => 'Enhancement',
                'slug' => 'enhancement',
                'color' => '#a2eeef',
                'description' => 'New feature or request',
                'keywords' => json_encode(['feature', 'enhancement', 'improve', 'add', 'new', 'request', 'implement', 'support']),
                'created' => $now,
                'modified' => $now,
            ],
        ])->save();

        // DevOps Orchestrator Agent
        $agentsTable = $this->table('agents');
        $agentsTable->insert([
            [
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
            ],
        ])->save();
    }
}
