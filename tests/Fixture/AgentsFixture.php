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
            'slug' => 'devops-orchestrator',
            'plugin' => 'DevOpsOrchestrator',
            'description' => 'Parses issue blocks from conversations and creates GitHub issues.',
            'is_enabled' => 1,
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4o',
            'instructions' => 'You are a helpful DevOps assistant.',
            'config' => null,
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ],
        [
            'id' => 2,
            'name' => 'No LLM Agent',
            'slug' => 'no-llm-agent',
            'plugin' => 'DevOpsOrchestrator',
            'description' => 'Agent without LLM configured.',
            'is_enabled' => 1,
            'llm_provider' => null,
            'llm_model' => null,
            'instructions' => null,
            'config' => null,
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ],
    ];
}
