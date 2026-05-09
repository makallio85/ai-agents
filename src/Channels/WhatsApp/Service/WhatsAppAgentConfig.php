<?php
declare(strict_types=1);

namespace App\Channels\WhatsApp\Service;

use App\Model\Entity\Agent;

/**
 * Resolved WhatsApp configuration for one agent.
 *
 * Built by WhatsAppConfigService from agent_contexts rows. Secrets
 * (access_token, app_secret) are decrypted on access; this object holds
 * them in memory only for the duration of a single request / job.
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
