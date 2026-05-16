<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Fixture for agent_integration_permissions.
 *
 * No pre-seeded records — every permission test seeds its own grants so
 * the deny-all default is exercised explicitly. Exists so the fixture
 * manager can truncate the table between tests.
 */
class AgentIntegrationPermissionsFixture extends TestFixture
{
    public array $records = [];
}
