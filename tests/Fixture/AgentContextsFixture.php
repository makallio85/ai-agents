<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Fixture for the agent_contexts table.
 *
 * No pre-seeded records — tests that need contexts create them during the test.
 * Exists so CakePHP's TruncateStrategy can reset the table between test runs.
 */
class AgentContextsFixture extends TestFixture
{
    public array $records = [];
}
