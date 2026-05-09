<?php
declare(strict_types=1);

namespace App\Messaging\Exception;

use RuntimeException;

/**
 * Raised when a handoff API call is invalid for the session's current state
 * (e.g. trying to assign a session that isn't pending_human, or a non-assigned
 * user attempting replyAsHuman).
 */
class HandoffStateException extends RuntimeException
{
}
