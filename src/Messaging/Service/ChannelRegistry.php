<?php
declare(strict_types=1);

namespace App\Messaging\Service;

use App\Messaging\Contract\ChannelTransportInterface;
use App\Messaging\Exception\UnknownChannelException;

/**
 * Maps channel names ('whatsapp', 'email', ...) to their transport implementations.
 *
 * Transports register themselves at bootstrap time. Code that needs to send or
 * parse for a specific channel resolves the transport through this registry,
 * keeping the dispatcher and inbound job free of any channel-specific imports.
 */
class ChannelRegistry
{
    /** @var array<string, ChannelTransportInterface> */
    private array $transports = [];

    public function register(ChannelTransportInterface $transport): void
    {
        $this->transports[$transport->name()] = $transport;
    }

    public function get(string $channel): ChannelTransportInterface
    {
        if (!isset($this->transports[$channel])) {
            throw new UnknownChannelException("No transport registered for channel '{$channel}'");
        }
        return $this->transports[$channel];
    }

    public function has(string $channel): bool
    {
        return isset($this->transports[$channel]);
    }

    /** @return array<string, ChannelTransportInterface> */
    public function all(): array
    {
        return $this->transports;
    }
}
