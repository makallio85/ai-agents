<?php
declare(strict_types=1);

namespace App\Messaging\Exception;

use RuntimeException;

/**
 * Thrown when a transport refuses a reactive send because the channel's
 * messaging window has closed. WhatsApp's 24h Service window is the
 * canonical case: outside the window, the caller must explicitly use
 * MessageDispatcher::proactive() with an approved template.
 */
class OutsideMessagingWindowException extends RuntimeException
{
}
