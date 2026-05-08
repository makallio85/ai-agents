<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Integration\GitHub;

use RuntimeException;

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
        return $this->statusCode === 403 || $this->statusCode === 429;
    }
}
