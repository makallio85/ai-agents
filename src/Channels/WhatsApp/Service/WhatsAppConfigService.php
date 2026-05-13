<?php
declare(strict_types=1);

namespace App\Channels\WhatsApp\Service;

use App\Channels\EncryptedConfigTrait;
use App\Model\Entity\AgentWhatsAppConfig;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

/**
 * Reads and writes per-agent WhatsApp configuration from agent_whatsapp_configs.
 *
 * Replaces the previous agent_contexts key-value storage ('whatsapp.*' keys).
 * The public API is unchanged — callers receive WhatsAppAgentConfig DTOs and
 * call setForAgent() to persist. Only the storage layer changed.
 *
 * The global Meta App secret is NOT stored per-agent — it is read from
 * Configure ('Channels.whatsapp.appSecret') and injected at DTO build time.
 * access_token is encrypted at rest (via EncryptedConfigTrait).
 */
class WhatsAppConfigService
{
    use EncryptedConfigTrait;

    public function findConfigByAgentId(int $agentId): ?WhatsAppAgentConfig
    {
        /** @var AgentWhatsAppConfig|null $row */
        $row = TableRegistry::getTableLocator()->get('AgentWhatsAppConfigs')
            ->find()
            ->contain(['Agents'])
            ->where(['AgentWhatsAppConfigs.agent_id' => $agentId])
            ->first();

        return $row !== null ? $this->buildFromRow($row) : null;
    }

    public function findConfigByPhoneNumberId(string $phoneNumberId): ?WhatsAppAgentConfig
    {
        /** @var AgentWhatsAppConfig|null $row */
        $row = TableRegistry::getTableLocator()->get('AgentWhatsAppConfigs')
            ->find()
            ->contain(['Agents'])
            ->where(['AgentWhatsAppConfigs.phone_number_id' => $phoneNumberId])
            ->first();

        return $row !== null ? $this->buildFromRow($row) : null;
    }

    /**
     * Returns config values for the admin UI. Sensitive fields are masked.
     *
     * @return array{phone_number_id: ?string, display_number: ?string, access_token_set: bool, welcome_template_name: ?string, enabled: bool, has_global_app_secret: bool}
     */
    public function readForUi(int $agentId): array
    {
        /** @var AgentWhatsAppConfig|null $row */
        $row = TableRegistry::getTableLocator()->get('AgentWhatsAppConfigs')
            ->find()
            ->where(['agent_id' => $agentId])
            ->first();

        return [
            'phone_number_id'      => $row?->phone_number_id,
            'display_number'       => $row?->display_number,
            'access_token_set'     => $row !== null && $row->access_token !== '',
            'welcome_template_name'=> $row?->welcome_template_name,
            'enabled'              => $row !== null && $row->enabled,
            'has_global_app_secret'=> trim((string)Configure::read('Channels.whatsapp.appSecret', '')) !== '',
        ];
    }

    /**
     * Creates or updates the WhatsApp config row for an agent.
     *
     * access_token is optional on update — if empty the existing encrypted
     * value is kept so the admin does not have to paste it back on every save.
     */
    public function setForAgent(
        int $agentId,
        string $phoneNumberId,
        string $displayNumber,
        ?string $accessToken,
        ?string $welcomeTemplateName,
        bool $enabled,
    ): void {
        $table = TableRegistry::getTableLocator()->get('AgentWhatsAppConfigs');

        /** @var AgentWhatsAppConfig|null $existing */
        $existing = $table->find()->where(['agent_id' => $agentId])->first();

        $data = [
            'agent_id'              => $agentId,
            'phone_number_id'       => $phoneNumberId,
            'display_number'        => $displayNumber,
            'welcome_template_name' => $welcomeTemplateName,
            'enabled'               => $enabled,
        ];

        if ($accessToken !== null && $accessToken !== '') {
            $data['access_token'] = $this->encrypt($accessToken);
        }

        if ($existing !== null) {
            $table->patchEntity($existing, $data);
            $table->saveOrFail($existing);
        } else {
            $entity = $table->newEntity($data);
            $table->saveOrFail($entity);
        }
    }

    private function buildFromRow(AgentWhatsAppConfig $row): ?WhatsAppAgentConfig
    {
        $appSecret = (string)Configure::read('Channels.whatsapp.appSecret', '');
        if ($row->phone_number_id === '' || $row->access_token === '' || $appSecret === '') {
            return null;
        }

        return new WhatsAppAgentConfig(
            agent: $row->agent,
            phoneNumberId: $row->phone_number_id,
            displayNumber: $row->display_number,
            accessToken: $this->decrypt($row->access_token),
            appSecret: $appSecret,
            welcomeTemplateName: $row->welcome_template_name,
            enabled: $row->enabled,
        );
    }
}
