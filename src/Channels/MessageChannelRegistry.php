<?php
declare(strict_types=1);

namespace App\Channels;

use App\Channels\Slack\Service\SlackConfigService;
use App\Channels\Slack\SlackMessageChannel;
use App\Channels\WhatsApp\Service\WhatsAppConfigService;
use App\Channels\WhatsApp\WhatsAppMessageChannel;

/**
 * Central registry of MessageChannelInterface implementations.
 *
 * The "MessageChannels" abstraction (issue #15) unifies how Slack, WhatsApp
 * and any future channel types are listed and configured per agent. The
 * registry is the single place callers look up channel implementations by
 * key — keeping AgentChannelsController and the admin UI free of any
 * channel-specific imports.
 *
 * The default() factory wires the built-in channels (Slack + WhatsApp)
 * using their existing config services. Tests construct an empty registry
 * and register fakes via register().
 */
class MessageChannelRegistry
{
    /** @var array<string, MessageChannelInterface> */
    private array $channels = [];

    public function register(MessageChannelInterface $channel): void
    {
        $this->channels[$channel->key()] = $channel;
    }

    public function get(string $key): ?MessageChannelInterface
    {
        return $this->channels[$key] ?? null;
    }

    /**
     * Returns all registered channels in insertion order so the admin UI
     * renders them deterministically.
     *
     * @return array<string, MessageChannelInterface>
     */
    public function all(): array
    {
        return $this->channels;
    }

    /**
     * Default registry with the application's built-in channel types.
     */
    public static function default(): self
    {
        $registry = new self();
        $registry->register(new SlackMessageChannel(new SlackConfigService()));
        $registry->register(new WhatsAppMessageChannel(new WhatsAppConfigService()));

        return $registry;
    }
}
