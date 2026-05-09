<?php
declare(strict_types=1);

namespace App\Integration\Speech;

use RuntimeException;

class SpeechException extends RuntimeException
{
    public function __construct(string $message, private readonly int $statusCode = 0)
    {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
