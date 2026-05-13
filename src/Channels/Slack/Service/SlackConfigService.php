<?php
declare(strict_types=1);

namespace App\Channels\Slack\Service;

use App\Channels\EncryptedConfigTrait;
use App\Model\Entity\AgentSlackConfig;
use Cake\ORM\TableRegistry;

/**
 * Reads and writes per-agent Slack configuration from agent_slack_configs.
 *
 * Replaces the previous agent_contexts key-value storage ('slack.*' keys).
 * The public API is unchanged — callers still receive SlackAgentConfig DTOs
 * and call setForAgent() to persist. Only the storage layer changed.
 *
 * Encrypted-at-rest fields: bot_token, signing_secret (via EncryptedConfigTrait).
 */
class SlackConfigService
{
    use EncryptedConfigTrait;

    public function findConfigByAgentId(int $agentId): ?SlackAgentConfig
    {
        /** @var AgentSlackConfig|null $row */
        $row = TableRegistry::getTableLocator()->get('AgentSlackConfigs')
            ->find()
            ->contain(['Agents'])
            ->where(['AgentSlackConfigs.agent_id' => $agentId])
            ->first();

        return $row !== null ? $this->buildFromRow($row) : null;
    }

    public function findConfigByAppId(string $appId): ?SlackAgentConfig
    {
        /** @var AgentSlackConfig|null $row */
        $row = TableRegistry::getTableLocator()->get('AgentSlackConfigs')
            ->find()
            ->contain(['Agents'])
            ->where(['AgentSlackConfigs.app_id' => $appId])
            ->first();

        return $row !== null ? $this->buildFromRow($row) : null;
    }

    /**
     * Returns config values for the admin UI. Sensitive fields are masked
     * to indicate whether they are set without exposing the value.
     *
     * @return array{app_id: ?string, bot_user_id: ?string, bot_token_set: bool, signing_secret_set: bool, team_id: ?string, enabled: bool}
     */
    public function readForUi(int $agentId): array
    {
        /** @var AgentSlackConfig|null $row */
        $row = TableRegistry::getTableLocator()->get('AgentSlackConfigs')
            ->find()
            ->where(['agent_id' => $agentId])
            ->first();

        if ($row === null) {
            return [
                'app_id'             => null,
                'bot_user_id'        => null,
                'bot_token_set'      => false,
                'signing_secret_set' => false,
                'team_id'            => null,
                'enabled'            => false,
            ];
        }

        return [
            'app_id'             => $row->app_id,
            'bot_user_id'        => $row->bot_user_id,
            'bot_token_set'      => $row->bot_token !== '',
            'signing_secret_set' => $row->signing_secret !== '',
            'team_id'            => $row->team_id,
            'enabled'            => $row->enabled,
        ];
    }

    /**
     * Creates or updates the Slack config row for an agent.
     *
     * bot_token and signing_secret are optional on update — if empty the
     * existing encrypted value is left untouched so the admin does not have
     * to paste secrets back in on every save.
     */
    public function setForAgent(
        int $agentId,
        string $appId,
        string $botUserId,
        ?string $botToken,
        ?string $signingSecret,
        ?string $teamId,
        bool $enabled,
    ): void {
        $table = TableRegistry::getTableLocator()->get('AgentSlackConfigs');

        /** @var AgentSlackConfig|null $existing */
        $existing = $table->find()->where(['agent_id' => $agentId])->first();

        $data = [
            'agent_id'    => $agentId,
            'app_id'      => $appId,
            'bot_user_id' => $botUserId,
            'team_id'     => $teamId,
            'enabled'     => $enabled,
        ];

        if ($botToken !== null && $botToken !== '') {
            $data['bot_token'] = $this->encrypt($botToken);
        }
        if ($signingSecret !== null && $signingSecret !== '') {
            $data['signing_secret'] = $this->encrypt($signingSecret);
        }

        if ($existing !== null) {
            $table->patchEntity($existing, $data);
            $table->saveOrFail($existing);
        } else {
            $entity = $table->newEntity($data);
            $table->saveOrFail($entity);
        }
    }

    private function buildFromRow(AgentSlackConfig $row): ?SlackAgentConfig
    {
        if ($row->app_id === '' || $row->bot_token === '' || $row->signing_secret === '') {
            return null;
        }

        return new SlackAgentConfig(
            agent: $row->agent,
            appId: $row->app_id,
            botUserId: $row->bot_user_id,
            botToken: $this->decrypt($row->bot_token),
            signingSecret: $this->decrypt($row->signing_secret),
            teamId: $row->team_id,
            enabled: $row->enabled,
        );
    }
}
