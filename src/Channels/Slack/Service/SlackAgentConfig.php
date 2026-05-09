<?php
declare(strict_types=1);

namespace App\Channels\Slack\Service;

use App\Model\Entity\Agent;

/**
 * Resolved Slack configuration for one agent.
 *
 * Each agent owns its own Slack App (each with its own bot user, signing
 * secret, and bot token). signingSecret is per-agent because Slack signs
 * webhooks with the App's shared secret — different App, different secret.
 */
class SlackAgentConfig
{
    public function __construct(
        public readonly Agent $agent,
        public readonly string $appId,
        public readonly string $botUserId,
        public readonly string $botToken,
        public readonly string $signingSecret,
        public readonly ?string $teamId,
        public readonly bool $enabled,
    ) {
    }
}
