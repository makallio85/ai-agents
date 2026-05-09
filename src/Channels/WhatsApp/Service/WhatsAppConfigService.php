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
 * Secrets (access_token, app_secret) are encrypted at rest using Cake's
 * Security::encrypt with App.encryptionKey, decrypted only when assembled
 * into a WhatsAppAgentConfig DTO for a single transport call.
 */
class WhatsAppConfigService
{
    public const KEY_PHONE_NUMBER_ID = 'whatsapp.phone_number_id';
    public const KEY_DISPLAY_NUMBER = 'whatsapp.display_number';
    public const KEY_ACCESS_TOKEN = 'whatsapp.access_token';
    public const KEY_APP_SECRET = 'whatsapp.app_secret';
    public const KEY_WELCOME_TEMPLATE = 'whatsapp.welcome_template_name';
    public const KEY_ENABLED = 'whatsapp.enabled';

    private const ENCRYPTED_KEYS = [
        self::KEY_ACCESS_TOKEN,
        self::KEY_APP_SECRET,
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
        // Phone-number-ids are stored in plaintext, so we can match directly.
        /** @var AgentContext|null $hit */
        $hit = $agentContexts->find()
            ->where([
                'key' => self::KEY_PHONE_NUMBER_ID,
                'value' => $phoneNumberId,
            ])
            ->first();
        if ($hit === null) {
            return null;
        }
        return $this->findConfigByAgentId($hit->agent_id);
    }

    public function setForAgent(
        int $agentId,
        string $phoneNumberId,
        string $displayNumber,
        string $accessToken,
        string $appSecret,
        ?string $welcomeTemplateName = null,
        bool $enabled = true,
    ): void {
        $this->upsert($agentId, self::KEY_PHONE_NUMBER_ID, $phoneNumberId);
        $this->upsert($agentId, self::KEY_DISPLAY_NUMBER, $displayNumber);
        $this->upsert($agentId, self::KEY_ACCESS_TOKEN, $accessToken);
        $this->upsert($agentId, self::KEY_APP_SECRET, $appSecret);
        if ($welcomeTemplateName !== null) {
            $this->upsert($agentId, self::KEY_WELCOME_TEMPLATE, $welcomeTemplateName);
        }
        $this->upsert($agentId, self::KEY_ENABLED, $enabled ? 'true' : 'false');
    }

    private function buildFromAgent(Agent $agent): ?WhatsAppAgentConfig
    {
        $values = [];
        foreach (($agent->agent_contexts ?? []) as $ctx) {
            if (!str_starts_with((string)$ctx->key, 'whatsapp.')) {
                continue;
            }
            $values[$ctx->key] = $ctx->value;
        }
        if (
            empty($values[self::KEY_PHONE_NUMBER_ID])
            || empty($values[self::KEY_ACCESS_TOKEN])
            || empty($values[self::KEY_APP_SECRET])
        ) {
            return null;
        }

        return new WhatsAppAgentConfig(
            agent: $agent,
            phoneNumberId: (string)$values[self::KEY_PHONE_NUMBER_ID],
            displayNumber: (string)($values[self::KEY_DISPLAY_NUMBER] ?? ''),
            accessToken: $this->decrypt((string)$values[self::KEY_ACCESS_TOKEN]),
            appSecret: $this->decrypt((string)$values[self::KEY_APP_SECRET]),
            welcomeTemplateName: $values[self::KEY_WELCOME_TEMPLATE] ?? null,
            enabled: ($values[self::KEY_ENABLED] ?? 'true') === 'true',
        );
    }

    private function upsert(int $agentId, string $key, string $value): void
    {
        $stored = in_array($key, self::ENCRYPTED_KEYS, true) ? $this->encrypt($value) : $value;
        $contexts = TableRegistry::getTableLocator()->get('AgentContexts');
        $existing = $contexts->find()->where(['agent_id' => $agentId, 'key' => $key])->first();
        if ($existing !== null) {
            $existing->value = $stored;
            $contexts->save($existing);
            return;
        }
        $entity = $contexts->newEntity([
            'agent_id' => $agentId,
            'key' => $key,
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
            // Backward-compat: not encrypted yet (e.g. seeded plain in dev).
            return $stored;
        }
        $plain = Security::decrypt($decoded, $this->encryptionKey());
        return $plain !== null ? $plain : $stored;
    }
}
