<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\TableRegistry;

/**
 * Manages the catalog of supported integration actions and the per-agent grants.
 *
 * Responsibilities:
 *   1. Expose the canonical catalog of (integration, action) pairs that
 *      agents can be granted. The catalog is the single source of truth
 *      consumed by the permissions UI, the API, and the enforcement layer
 *      — new actions added to GitHubToolProvider MUST also be registered
 *      here, otherwise they cannot be granted.
 *   2. Load the current grant set for a specific agent, returned as a
 *      lightweight in-memory check object that the agentic loop uses to
 *      authorise individual tool calls without re-querying the database.
 *   3. Atomically replace the grant set for an agent when the UI saves —
 *      delete-all + insert-checked, validated against the catalog so the
 *      table never contains unknown actions.
 *
 * The deny-all default is enforced here, not in the database: if no row
 * exists for a triple, the permission check returns false.
 *
 * Tool-to-action mapping for GitHub lives in this class (toolActionMap)
 * so AgentLoopService can ask "what permission does tool X require?" in
 * one place. Keep this map in sync with GitHubToolProvider::getDefinitions().
 */
class AgentIntegrationPermissionService
{
    public const INTEGRATION_GITHUB = 'github';

    /**
     * Canonical catalog of all grantable actions.
     *
     * Keyed by integration slug, each value is a list of action descriptors
     * (action key + human-readable label). The action key is the value
     * persisted in the `action` column and checked at enforcement time.
     *
     * Action keys are namespaced as `<integration>.<resource>.<verb>` so
     * they remain unique across integrations and self-describing in logs.
     *
     * @var array<string, list<array{action: string, label: string}>>
     */
    private const CATALOG = [
        self::INTEGRATION_GITHUB => [
            ['action' => 'github.repos.read',    'label' => 'List and view repositories'],
            ['action' => 'github.files.read',    'label' => 'Read file contents'],
            ['action' => 'github.issues.read',   'label' => 'List and view issues'],
            ['action' => 'github.issues.write',  'label' => 'Create, comment on, and close issues'],
            ['action' => 'github.commits.read',  'label' => 'List and view commits'],
            ['action' => 'github.pulls.read',    'label' => 'List and view pull requests'],
        ],
    ];

    /**
     * Maps GitHub tool names (as exposed to the LLM) to the action the
     * caller must have been granted in order to execute them.
     *
     * Kept here, not in GitHubToolProvider, so the enforcement layer is
     * the single source of truth and so a tool cannot be silently added
     * without an explicit permission decision being made for it.
     *
     * @var array<string, string>
     */
    private const TOOL_ACTION_MAP = [
        'github_list_repos'              => 'github.repos.read',
        'github_get_file'                => 'github.files.read',
        'github_list_directory'          => 'github.files.read',
        'github_list_issues'             => 'github.issues.read',
        'github_get_issue'               => 'github.issues.read',
        'github_create_issue'            => 'github.issues.write',
        'github_comment_on_issue'        => 'github.issues.write',
        'github_close_issue'             => 'github.issues.write',
        'github_list_commits'            => 'github.commits.read',
        'github_get_commit'              => 'github.commits.read',
        'github_list_pull_requests'      => 'github.pulls.read',
        'github_get_pull_request'        => 'github.pulls.read',
        'github_get_pull_request_files'  => 'github.pulls.read',
        'github_get_pull_request_commits' => 'github.pulls.read',
    ];

    /**
     * Returns the full catalog, optionally restricted to a single integration.
     *
     * @return array<string, list<array{action: string, label: string}>>
     */
    public function getCatalog(?string $integration = null): array
    {
        if ($integration === null) {
            return self::CATALOG;
        }

        return isset(self::CATALOG[$integration])
            ? [$integration => self::CATALOG[$integration]]
            : [];
    }

    /**
     * Returns the flat list of every action key that exists in the catalog.
     *
     * @return list<string>
     */
    public function getAllActionKeys(): array
    {
        $keys = [];
        foreach (self::CATALOG as $actions) {
            foreach ($actions as $descriptor) {
                $keys[] = $descriptor['action'];
            }
        }

        return $keys;
    }

    /**
     * Returns the action a given tool name requires, or null if the tool is
     * not gated by a permission.
     */
    public function getActionForTool(string $toolName): ?string
    {
        return self::TOOL_ACTION_MAP[$toolName] ?? null;
    }

    /**
     * Loads the current grant set for the agent and wraps it in a check
     * object for fast in-memory authorisation.
     */
    public function loadForAgent(int $agentId): AgentPermissionSet
    {
        $table = TableRegistry::getTableLocator()->get('AgentIntegrationPermissions');

        $rows = $table->find()
            ->where(['AgentIntegrationPermissions.agent_id' => $agentId])
            ->select(['integration', 'action'])
            ->disableHydration()
            ->all()
            ->toList();

        $grants = [];
        foreach ($rows as $row) {
            $grants[] = (string)$row['action'];
        }

        return new AgentPermissionSet($grants);
    }

    /**
     * Replaces all permission grants for the given agent with the supplied
     * action keys. Unknown actions are silently dropped so that a stale UI
     * client cannot persist gibberish into the table.
     *
     * @param list<string> $actionKeys
     */
    public function replaceForAgent(int $agentId, array $actionKeys): void
    {
        $table = TableRegistry::getTableLocator()->get('AgentIntegrationPermissions');
        $valid = array_values(array_unique(array_intersect(
            $actionKeys,
            $this->getAllActionKeys(),
        )));

        $connection = $table->getConnection();
        $connection->transactional(function () use ($table, $agentId, $valid): void {
            $table->deleteAll(['agent_id' => $agentId]);
            foreach ($valid as $action) {
                $integration = (string)substr($action, 0, (int)strpos($action, '.'));
                $entity = $table->newEntity([
                    'agent_id'    => $agentId,
                    'integration' => $integration,
                    'action'      => $action,
                ]);
                $table->saveOrFail($entity);
            }
        });
    }
}
