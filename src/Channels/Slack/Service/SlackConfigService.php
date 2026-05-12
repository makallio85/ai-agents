<?php
declare(strict_types=1);

namespace App\Channels\Slack\Service;

use App\Model\Entity\Agent;
use App\Model\Entity\AgentContext;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;

/**
 * Reads and writes per-agent Slack configuration stored as key-value rows
 * in agent_contexts under the 'slack.*' namespace.
 *
 * Encrypted-at-rest fields: bot_token, signing_secret. The Slack App is
 * scoped per-agent (one Slack App = one bot user = one agent), so the
 * signing secret really is per-agent — unlike WhatsApp where the Meta App
 * secret was lifted to env.
 */
class SlackConfigService
{
    public const KEY_APP_ID = 'slack.app_id';
    public const KEY_BOT_USER_ID = 'slack.bot_user_id';
    public const KEY_BOT_TOKEN = 'slack.bot_token';
    public const KEY_SIGNING_SECRET = 'slack.signing_secret';
    public const KEY_TEAM_ID = 'slack.team_id';
    public const KEY_ENABLED = 'slack.enabled';

    private const ENCRYPTED_KEYS = [
        self::KEY_BOT_TOKEN,
        self::KEY_SIGNING_SECRET,
    ];

    public function findConfigByAgentId(int $agentId): ?SlackAgentConfig
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

    public function findConfigByAppId(string $appId): ?SlackAgentConfig
    {
        $agentContexts = TableRegistry::getTableLocator()->get('AgentContexts');
        /** @var AgentContext|null $hit */
        $hit = $agentContexts->find()
            ->where(['context_key' => self::KEY_APP_ID, 'value' => $appId])
            ->first();
        if ($hit === null) {
            return null;
        }
        return $this->findConfigByAgentId($hit->agent_id);
    }

    /**
     * @return array{app_id: ?string, bot_user_id: ?string, bot_token_set: bool, signing_secret_set: bool, team_id: ?string, enabled: bool}
     */
    public function readForUi(int $agentId): array
    {
        $values = $this->loadValues($agentId);
        return [
            'app_id' => $values[self::KEY_APP_ID] ?? null,
            'bot_user_id' => $values[self::KEY_BOT_USER_ID] ?? null,
            'bot_token_set' => !empty($values[self::KEY_BOT_TOKEN]),
            'signing_secret_set' => !empty($values[self::KEY_SIGNING_SECRET]),
            'team_id' => $values[self::KEY_TEAM_ID] ?? null,
            'enabled' => ($values[self::KEY_ENABLED] ?? 'false') === 'true',
        ];
    }

    public function setForAgent(
        int $agentId,
        string $appId,
        string $botUserId,
        ?string $botToken,
        ?string $signingSecret,
        ?string $teamId,
        bool $enabled,
    ): void {
        $this->upsert($agentId, self::KEY_APP_ID, $appId);
        $this->upsert($agentId, self::KEY_BOT_USER_ID, $botUserId);
        if ($botToken !== null && $botToken !== '') {
            $this->upsert($agentId, self::KEY_BOT_TOKEN, $botToken);
        }
        if ($signingSecret !== null && $signingSecret !== '') {
            $this->upsert($agentId, self::KEY_SIGNING_SECRET, $signingSecret);
        }
        if ($teamId !== null) {
            $this->upsert($agentId, self::KEY_TEAM_ID, $teamId);
        }
        $this->upsert($agentId, self::KEY_ENABLED, $enabled ? 'true' : 'false');
    }

    private function buildFromAgent(Agent $agent): ?SlackAgentConfig
    {
        $values = [];
        foreach (($agent->agent_contexts ?? []) as $ctx) {
            if (!str_starts_with((string)$ctx->context_key, 'slack.')) {
                continue;
            }
            $values[$ctx->context_key] = $ctx->value;
        }
        if (
            empty($values[self::KEY_APP_ID])
            || empty($values[self::KEY_BOT_TOKEN])
            || empty($values[self::KEY_SIGNING_SECRET])
        ) {
            return null;
        }

        return new SlackAgentConfig(
            agent: $agent,
            appId: (string)$values[self::KEY_APP_ID],
            botUserId: (string)($values[self::KEY_BOT_USER_ID] ?? ''),
            botToken: $this->decrypt((string)$values[self::KEY_BOT_TOKEN]),
            signingSecret: $this->decrypt((string)$values[self::KEY_SIGNING_SECRET]),
            teamId: $values[self::KEY_TEAM_ID] ?? null,
            enabled: ($values[self::KEY_ENABLED] ?? 'true') === 'true',
        );
    }

    /** @return array<string, string> */
    private function loadValues(int $agentId): array
    {
        $contexts = TableRegistry::getTableLocator()->get('AgentContexts');
        $rows = $contexts->find()
            ->where(['agent_id' => $agentId, 'context_key LIKE' => 'slack.%'])
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
