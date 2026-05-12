<?php
declare(strict_types=1);

namespace App\Test\TestCase\Channels\Slack;

use App\Channels\Slack\Service\SlackConfigService;
use Cake\TestSuite\TestCase;

/**
 * Covers SlackConfigService save/read round-trip against a real test DB.
 *
 * Bug reproduced: the agent_contexts table has a column named 'key' which is
 * a reserved word in MariaDB. CakePHP builds unquoted queries like
 * WHERE key LIKE 'slack.%' which throw SQLSTATE[42000] syntax errors.
 * The fix is to rename the column to 'context_key'.
 *
 * These tests must be RED before the column rename migration is applied
 * and GREEN after.
 */
class SlackConfigServiceTest extends TestCase
{
    /**
     * @var array<string>
     */
    protected array $fixtures = [
        'app.Agents',
        'app.AgentContexts',
    ];

    private SlackConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SlackConfigService();
    }

    /**
     * Saving and immediately reading back config must return the saved values.
     *
     * Reproduces the reserved-word bug: setForAgent() calls upsert() which
     * runs WHERE key = ? and INSERT with key column — both fail in MariaDB
     * when 'key' is unquoted.
     */
    public function testSetForAgentPersistsAndReadsBack(): void
    {
        $this->service->setForAgent(
            agentId: 1,
            appId: 'A0123TEST',
            botUserId: 'U0123TEST',
            botToken: 'xoxb-test-token',
            signingSecret: 'test-signing-secret',
            teamId: 'T0123TEST',
            enabled: true,
        );

        $result = $this->service->readForUi(1);

        $this->assertSame('A0123TEST', $result['app_id']);
        $this->assertSame('U0123TEST', $result['bot_user_id']);
        $this->assertTrue($result['bot_token_set']);
        $this->assertTrue($result['signing_secret_set']);
        $this->assertSame('T0123TEST', $result['team_id']);
        $this->assertTrue($result['enabled']);
    }

    /**
     * A second save must update existing rows, not create duplicates.
     *
     * Reproduces the same reserved-word bug on the UPDATE path of upsert().
     */
    public function testSetForAgentUpdatesExistingValues(): void
    {
        $this->service->setForAgent(
            agentId: 1,
            appId: 'A_ORIGINAL',
            botUserId: 'U_ORIGINAL',
            botToken: 'xoxb-original',
            signingSecret: 'secret-original',
            teamId: null,
            enabled: false,
        );

        $this->service->setForAgent(
            agentId: 1,
            appId: 'A_UPDATED',
            botUserId: 'U_UPDATED',
            botToken: null, // keep existing
            signingSecret: null, // keep existing
            teamId: 'T_NEW',
            enabled: true,
        );

        $result = $this->service->readForUi(1);

        $this->assertSame('A_UPDATED', $result['app_id']);
        $this->assertSame('U_UPDATED', $result['bot_user_id']);
        $this->assertTrue($result['bot_token_set'], 'Existing bot token must be preserved');
        $this->assertSame('T_NEW', $result['team_id']);
        $this->assertTrue($result['enabled']);
    }

    /**
     * readForUi on an agent with no config must return safe empty defaults
     * without throwing a SQL error.
     */
    public function testReadForUiReturnsEmptyDefaultsWhenNoConfig(): void
    {
        $result = $this->service->readForUi(1);

        $this->assertNull($result['app_id']);
        $this->assertNull($result['bot_user_id']);
        $this->assertFalse($result['bot_token_set']);
        $this->assertFalse($result['signing_secret_set']);
        $this->assertNull($result['team_id']);
        $this->assertFalse($result['enabled']);
    }
}
