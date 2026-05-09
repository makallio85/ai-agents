<?php
declare(strict_types=1);

namespace App\Channels\WhatsApp\Service;

use App\Model\Entity\Agent;

/**
 * Resolved WhatsApp configuration for one agent.
 *
 * Per-agent fields (phone_number_id, access_token, ...) come from
 * agent_contexts. The Meta App secret is global (Configure::read
 * 'Channels.whatsapp.appSecret') because it is per-App, not per-phone — all
 * agents that share a Meta App share the same secret. WhatsAppConfigService
 * resolves both into one immutable DTO.
 */
class WhatsAppAgentConfig
{
    public function __construct(
        public readonly Agent $agent,
        public readonly string $phoneNumberId,
        public readonly string $displayNumber,
        public readonly string $accessToken,
        public readonly string $appSecret,
        public readonly ?string $welcomeTemplateName,
        public readonly bool $enabled,
    ) {
    }
}
