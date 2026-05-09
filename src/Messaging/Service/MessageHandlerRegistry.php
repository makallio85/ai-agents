<?php
declare(strict_types=1);

namespace App\Messaging\Service;

use App\Messaging\Contract\MessageHandlerInterface;

/**
 * Maps an agent's plugin slug to the MessageHandlerInterface implementation
 * that should service its inbound messages.
 *
 * Plugins call register() from their Plugin::bootstrap(). Agents whose
 * agent.plugin slug has no override fall through to the default handler
 * (App\Messaging\Service\LlmHandler) — meaning any agent with an LLM
 * provider configured gets WhatsApp / email / future channels for free.
 */
class MessageHandlerRegistry
{
    /** @var array<string, MessageHandlerInterface> */
    private array $handlers = [];

    public function __construct(
        private readonly MessageHandlerInterface $defaultHandler,
    ) {
    }

    public function register(string $pluginSlug, MessageHandlerInterface $handler): void
    {
        $this->handlers[$pluginSlug] = $handler;
    }

    public function resolve(?string $pluginSlug): MessageHandlerInterface
    {
        if ($pluginSlug !== null && isset($this->handlers[$pluginSlug])) {
            return $this->handlers[$pluginSlug];
        }
        return $this->defaultHandler;
    }
}
