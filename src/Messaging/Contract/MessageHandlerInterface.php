<?php
declare(strict_types=1);

namespace App\Messaging\Contract;

use App\Model\Entity\Agent;
use App\Model\Entity\ChatMessage;
use App\Model\Entity\ChatSession;

/**
 * Contract for an agent's reply behaviour.
 *
 * Every inbound message that reaches an agent (regardless of channel) is
 * delegated to the handler registered for that agent's plugin. The default
 * handler is App\Messaging\Service\LlmHandler, which streams the agent's
 * configured LLM. Plugins implement this interface only when they need
 * custom command logic on top of (or instead of) the LLM.
 *
 * Handlers must produce their reply through MessageDispatcher::reply() so
 * that channel-specific concerns (24h windows, templates, threading) are
 * resolved by the transport layer rather than duplicated in every plugin.
 */
interface MessageHandlerInterface
{
    public function handleMessage(Agent $agent, ChatSession $session, ChatMessage $inbound): void;
}
