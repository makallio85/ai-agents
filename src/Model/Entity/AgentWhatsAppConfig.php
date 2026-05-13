<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * ORM entity for agent_whatsapp_configs.
 *
 * Stores the per-agent WhatsApp Business API credentials. access_token is
 * encrypted at rest by WhatsAppConfigService. The global Meta App secret
 * is not stored here — it lives in env/Configure and is injected when
 * WhatsAppConfigService assembles a WhatsAppAgentConfig DTO.
 *
 * @property int $id
 * @property int $agent_id
 * @property string $phone_number_id
 * @property string $display_number
 * @property string $access_token
 * @property string|null $welcome_template_name
 * @property bool $enabled
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\Agent $agent
 */
class AgentWhatsAppConfig extends Entity
{
    /** @var array<string, bool> */
    protected array $_accessible = [
        'agent_id'              => true,
        'phone_number_id'       => true,
        'display_number'        => true,
        'access_token'          => true,
        'welcome_template_name' => true,
        'enabled'               => true,
        'agent'                 => true,
    ];
}
