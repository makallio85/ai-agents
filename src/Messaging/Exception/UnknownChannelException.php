<?php
declare(strict_types=1);

namespace App\Messaging\Exception;

use RuntimeException;

/**
 * Raised when ChannelRegistry is asked for a channel that no transport has registered.
 */
class UnknownChannelException extends RuntimeException
{
}
