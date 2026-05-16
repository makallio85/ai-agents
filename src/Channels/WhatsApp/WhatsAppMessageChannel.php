<?php
declare(strict_types=1);

namespace App\Channels\WhatsApp;

use App\Channels\MessageChannelInterface;
use App\Channels\WhatsApp\Service\WhatsAppConfigService;
use InvalidArgumentException;

/**
 * WhatsApp channel definition for the MessageChannelRegistry.
 *
 * Wraps WhatsAppConfigService and exposes it through the uniform channel
 * contract. The Meta App secret stays global (shared across all agents on
 * the same Meta App) and is read from Configure inside the underlying
 * service — only the per-agent phone number, access token and welcome
 * template name flow through this class.
 */
class WhatsAppMessageChannel implements MessageChannelInterface
{
    public function __construct(private WhatsAppConfigService $service)
    {
    }

    public function key(): string
    {
        return 'whatsapp';
    }

    public function label(): string
    {
        return 'WhatsApp';
    }

    public function description(): string
    {
        return 'One Meta phone number per agent. The Meta App secret is shared across all agents and lives in env (WHATSAPP_APP_SECRET).';
    }

    public function readForUi(int $agentId): array
    {
        return $this->service->readForUi($agentId);
    }

    public function setForAgent(int $agentId, array $data): array
    {
        $phoneNumberId = trim((string)($data['phone_number_id'] ?? ''));
        $displayNumber = trim((string)($data['display_number'] ?? ''));
        if ($phoneNumberId === '' || $displayNumber === '') {
            throw new InvalidArgumentException('phone_number_id and display_number are required');
        }

        $accessToken = isset($data['access_token']) ? trim((string)$data['access_token']) : null;
        $template = isset($data['welcome_template_name']) ? trim((string)$data['welcome_template_name']) : null;
        $enabled = (bool)($data['enabled'] ?? false);

        $this->service->setForAgent(
            agentId: $agentId,
            phoneNumberId: $phoneNumberId,
            displayNumber: $displayNumber,
            accessToken: ($accessToken === null || $accessToken === '') ? null : $accessToken,
            welcomeTemplateName: ($template === null || $template === '') ? null : $template,
            enabled: $enabled,
        );

        return $this->service->readForUi($agentId);
    }
}
