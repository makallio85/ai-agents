<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Dedicated table for per-agent Slack channel configuration.
 *
 * Replaces the 'slack.*' key-value rows previously stored in agent_contexts.
 * One row per agent — an agent either has a Slack config or doesn't.
 * Sensitive fields (bot_token, signing_secret) are stored encrypted by
 * SlackConfigService using CakePHP Security::encrypt.
 */
class CreateAgentSlackConfigs extends BaseMigration
{
    public function change(): void
    {
        $this->table('agent_slack_configs')
            ->addColumn('agent_id', 'integer', ['null' => false])
            ->addColumn('app_id', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('bot_user_id', 'string', ['limit' => 255, 'null' => false, 'default' => ''])
            ->addColumn('bot_token', 'text', ['null' => false])
            ->addColumn('signing_secret', 'text', ['null' => false])
            ->addColumn('team_id', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('enabled', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addForeignKey('agent_id', 'agents', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addIndex(['agent_id'], ['unique' => true])
            ->create();
    }
}
