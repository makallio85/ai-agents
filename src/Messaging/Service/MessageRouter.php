<?php
declare(strict_types=1);

namespace App\Messaging\Service;

use App\Messaging\Dto\InboundEnvelope;
use App\Model\Entity\Agent;
use App\Model\Entity\User;

/**
 * Resolves an InboundEnvelope to its target Agent + User using the channel
 * transport's resolvers. Extracted from ProcessInboundMessageJob for testability.
 */
class MessageRouter
{
    public function __construct(
        private readonly ChannelRegistry $channels,
    ) {
    }

    public function resolveAgent(InboundEnvelope $envelope): ?Agent
    {
        return $this->channels->get($envelope->channel)
            ->resolveAgentByExternalAccount($envelope->externalAccountId);
    }

    public function resolveUser(InboundEnvelope $envelope): ?User
    {
        return $this->channels->get($envelope->channel)
            ->resolveUserByExternalIdentifier($envelope->externalIdentifier);
    }
}
