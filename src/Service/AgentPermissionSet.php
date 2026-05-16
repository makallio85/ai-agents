<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Immutable in-memory snapshot of the actions granted to a specific agent.
 *
 * Built once per agent loop run by AgentIntegrationPermissionService and
 * consulted by the enforcement layer (GitHubToolProvider / AgentLoopService)
 * before every tool call. Keeping the snapshot immutable avoids subtle bugs
 * where a stale row read inside one turn would silently re-authorise a tool
 * call later in the same turn.
 *
 * Carries no behaviour beyond membership tests — saving and reloading is
 * the service's job.
 */
final class AgentPermissionSet
{
    /** @var array<string, true> */
    private readonly array $granted;

    /**
     * @param list<string> $grantedActions Action keys (e.g. `github.issues.read`).
     */
    public function __construct(array $grantedActions)
    {
        $map = [];
        foreach ($grantedActions as $action) {
            $map[$action] = true;
        }
        $this->granted = $map;
    }

    /**
     * True when the supplied action key has been granted to the agent.
     */
    public function has(string $action): bool
    {
        return isset($this->granted[$action]);
    }

    /**
     * True when the agent has at least one grant whose action key begins
     * with `{$integration}.` — used by the agentic loop to decide whether
     * to register the integration's tool definitions with the LLM at all.
     */
    public function hasAnyForIntegration(string $integration): bool
    {
        $prefix = $integration . '.';
        foreach ($this->granted as $action => $_) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return array_keys($this->granted);
    }
}
