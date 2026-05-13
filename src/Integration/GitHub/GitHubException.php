<?php
declare(strict_types=1);

namespace App\Integration\GitHub;

use RuntimeException;

/**
 * Represents a failed GitHub API call.
 *
 * Carries the HTTP status code so callers can distinguish rate-limit errors
 * (429) from auth/scope errors (403) and not-found errors (404) without
 * string-matching the message.
 *
 * Used by GitHubClient and surfaced through GitHubToolProvider to the LLM
 * as a TOOL_ERROR string so the agent can report the exact failure reason.
 */
class GitHubException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isRateLimited(): bool
    {
        return $this->statusCode === 429;
    }
}
