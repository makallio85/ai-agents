<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Dedicated table for per-agent WhatsApp channel configuration.
 *
 * Replaces the 'whatsapp.*' key-value rows previously stored in agent_contexts.
 * One row per agent. access_token is stored encrypted by WhatsAppConfigService.
 * The global Meta App secret stays in env/Configure (shared across all agents
 * on the same Meta App).
 */
class CreateAgentWhatsAppConfigs extends BaseMigration
{
    public function change(): void
    {
        $this->table('agent_whatsapp_configs')
            ->addColumn('agent_id', 'integer', ['null' => false])
            ->addColumn('phone_number_id', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('display_number', 'string', ['limit' => 50, 'null' => false, 'default' => ''])
            ->addColumn('access_token', 'text', ['null' => false])
            ->addColumn('welcome_template_name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('enabled', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addForeignKey('agent_id', 'agents', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addIndex(['agent_id'], ['unique' => true])
            ->create();
    }
}
