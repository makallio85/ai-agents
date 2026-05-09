<?php
declare(strict_types=1);

namespace App\Channels\WhatsApp;

use RuntimeException;

class WhatsAppException extends RuntimeException
{
    public function __construct(string $message, private readonly int $statusCode = 0)
    {
        parent::__construct($message, $statusCode);
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
