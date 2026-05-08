<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class AgentsFixture extends TestFixture
{
    public array $records = [
        [
            'id' => 1,
            'name' => 'DevOps Orchestrator',
            'plugin_name' => 'DevOpsOrchestrator',
            'description' => 'Parses issue blocks from conversations and creates GitHub issues.',
            'config' => null,
            'is_active' => 1,
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ],
    ];
}
