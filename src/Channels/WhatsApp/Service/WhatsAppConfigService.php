<?php
declare(strict_types=1);

namespace App\Channels\WhatsApp\Service;

use App\Model\Entity\Agent;
use App\Model\Entity\AgentContext;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;

/**
 * Reads and writes the WhatsApp configuration for each agent, stored as
 * key-value rows in agent_contexts under the 'whatsapp.*' namespace.
 *
 * Per-agent secrets (access_token) are encrypted at rest using Cake's
 * Security::encrypt with the application's encryption key, and decrypted
 * only when assembled into a WhatsAppAgentConfig DTO for a single transport
 * call. The Meta App secret is NOT per-agent — it is read from
 * Configure::read('Channels.whatsapp.appSecret').
 */
class WhatsAppConfigService
{
    public const KEY_PHONE_NUMBER_ID = 'whatsapp.phone_number_id';
    public const KEY_DISPLAY_NUMBER = 'whatsapp.display_number';
    public const KEY_ACCESS_TOKEN = 'whatsapp.access_token';
    public const KEY_WELCOME_TEMPLATE = 'whatsapp.welcome_template_name';
    public const KEY_ENABLED = 'whatsapp.enabled';

    private const ENCRYPTED_KEYS = [
        self::KEY_ACCESS_TOKEN,
    ];

    public function findConfigByAgentId(int $agentId): ?WhatsAppAgentConfig
    {
        /** @var Agent|null $agent */
        $agent = TableRegistry::getTableLocator()->get('Agents')
            ->find()
            ->contain(['AgentContexts'])
            ->where(['Agents.id' => $agentId])
            ->first();
        if ($agent === null) {
            return null;
        }
        return $this->buildFromAgent($agent);
    }

    public function findConfigByPhoneNumberId(string $phoneNumberId): ?WhatsAppAgentConfig
    {
        $agentContexts = TableRegistry::getTableLocator()->get('AgentContexts');
        /** @var AgentContext|null $hit */
        $hit = $agentContexts->find()
            ->where([
                'context_key' => self::KEY_PHONE_NUMBER_ID,
                'value' => $phoneNumberId,
            ])
            ->first();
        if ($hit === null) {
            return null;
        }
        return $this->findConfigByAgentId($hit->agent_id);
    }

    /**
     * Returns the per-agent settings as a plain associative array suitable for
     * the admin UI. Sensitive values are masked.
     *
     * @return array{phone_number_id: ?string, display_number: ?string, access_token_set: bool, welcome_template_name: ?string, enabled: bool, has_global_app_secret: bool}
     */
    public function readForUi(int $agentId): array
    {
        $values = $this->loadValues($agentId);
        return [
            'phone_number_id' => $values[self::KEY_PHONE_NUMBER_ID] ?? null,
            'display_number' => $values[self::KEY_DISPLAY_NUMBER] ?? null,
            'access_token_set' => !empty($values[self::KEY_ACCESS_TOKEN]),
            'welcome_template_name' => $values[self::KEY_WELCOME_TEMPLATE] ?? null,
            'enabled' => ($values[self::KEY_ENABLED] ?? 'false') === 'true',
            'has_global_app_secret' => trim((string)Configure::read('Channels.whatsapp.appSecret', '')) !== '',
        ];
    }

    /**
     * Writes config from the admin UI. accessToken is optional — if empty
     * the existing value is left untouched (so the admin doesn't have to
     * paste it back in on every edit).
     */
    public function setForAgent(
        int $agentId,
        string $phoneNumberId,
        string $displayNumber,
        ?string $accessToken,
        ?string $welcomeTemplateName,
        bool $enabled,
    ): void {
        $this->upsert($agentId, self::KEY_PHONE_NUMBER_ID, $phoneNumberId);
        $this->upsert($agentId, self::KEY_DISPLAY_NUMBER, $displayNumber);
        if ($accessToken !== null && $accessToken !== '') {
            $this->upsert($agentId, self::KEY_ACCESS_TOKEN, $accessToken);
        }
        if ($welcomeTemplateName !== null) {
            $this->upsert($agentId, self::KEY_WELCOME_TEMPLATE, $welcomeTemplateName);
        }
        $this->upsert($agentId, self::KEY_ENABLED, $enabled ? 'true' : 'false');
    }

    private function buildFromAgent(Agent $agent): ?WhatsAppAgentConfig
    {
        $values = [];
        foreach (($agent->agent_contexts ?? []) as $ctx) {
            if (!str_starts_with((string)$ctx->context_key, 'whatsapp.')) {
                continue;
            }
            $values[$ctx->context_key] = $ctx->value;
        }
        $appSecret = (string)Configure::read('Channels.whatsapp.appSecret', '');
        if (
            empty($values[self::KEY_PHONE_NUMBER_ID])
            || empty($values[self::KEY_ACCESS_TOKEN])
            || $appSecret === ''
        ) {
            return null;
        }

        return new WhatsAppAgentConfig(
            agent: $agent,
            phoneNumberId: (string)$values[self::KEY_PHONE_NUMBER_ID],
            displayNumber: (string)($values[self::KEY_DISPLAY_NUMBER] ?? ''),
            accessToken: $this->decrypt((string)$values[self::KEY_ACCESS_TOKEN]),
            appSecret: $appSecret,
            welcomeTemplateName: $values[self::KEY_WELCOME_TEMPLATE] ?? null,
            enabled: ($values[self::KEY_ENABLED] ?? 'true') === 'true',
        );
    }

    /** @return array<string, string> */
    private function loadValues(int $agentId): array
    {
        $contexts = TableRegistry::getTableLocator()->get('AgentContexts');
        $rows = $contexts->find()
            ->where(['agent_id' => $agentId, 'context_key LIKE' => 'whatsapp.%'])
            ->all();
        $values = [];
        foreach ($rows as $row) {
            /** @var \App\Model\Entity\AgentContext $row */
            $values[(string)$row->context_key] = (string)$row->value;
        }
        return $values;
    }

    private function upsert(int $agentId, string $key, string $value): void
    {
        $stored = in_array($key, self::ENCRYPTED_KEYS, true) ? $this->encrypt($value) : $value;
        $contexts = TableRegistry::getTableLocator()->get('AgentContexts');
        /** @var \App\Model\Entity\AgentContext|null $existing */
        $existing = $contexts->find()->where(['agent_id' => $agentId, 'context_key' => $key])->first();
        if ($existing !== null) {
            $existing->value = $stored;
            $contexts->save($existing);
            return;
        }
        $entity = $contexts->newEntity([
            'agent_id' => $agentId,
            'context_key' => $key,
            'value' => $stored,
        ]);
        $contexts->save($entity);
    }

    private function encryptionKey(): string
    {
        $key = (string)Configure::read('Security.salt', '');
        return $key !== '' ? $key : Configure::readOrFail('App.encryptionKey');
    }

    private function encrypt(string $plain): string
    {
        return base64_encode(Security::encrypt($plain, $this->encryptionKey()));
    }

    private function decrypt(string $stored): string
    {
        $decoded = base64_decode($stored, true);
        if ($decoded === false) {
            return $stored;
        }
        $plain = Security::decrypt($decoded, $this->encryptionKey());
        return $plain !== null ? $plain : $stored;
    }
}
