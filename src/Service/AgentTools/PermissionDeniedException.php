<?php
declare(strict_types=1);

namespace App\Service\AgentTools;

use RuntimeException;

/**
 * Thrown by a tool provider when the agent attempts to invoke a tool whose
 * required action has not been granted via the agent_integration_permissions
 * table.
 *
 * Caught by AgentLoopService so the denial can be (a) reported to the LLM as
 * a TOOL_ERROR result the model surfaces to the user verbatim, and (b)
 * recorded in agent_logs for audit purposes.
 *
 * Carries the required action and tool name so the log entry and the LLM
 * error message can be assembled by the catcher without re-parsing.
 */
class PermissionDeniedException extends RuntimeException
{
    public function __construct(
        public readonly string $tool,
        public readonly string $requiredAction,
    ) {
        parent::__construct(sprintf(
            'Agent does not have permission to perform "%s" (required for tool %s).',
            $requiredAction,
            $tool,
        ));
    }
}
