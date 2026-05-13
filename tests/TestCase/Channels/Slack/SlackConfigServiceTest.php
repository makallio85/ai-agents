<?php
declare(strict_types=1);

namespace App\Test\TestCase\Channels\Slack;

use App\Channels\Slack\Service\SlackConfigService;
use Cake\TestSuite\TestCase;

/**
 * Covers SlackConfigService save/read round-trip against the agent_slack_configs table.
 *
 * After issue #15 refactor, config is stored in a dedicated structured table
 * instead of the agent_contexts key-value store. Tests verify:
 * - create path (no existing row)
 * - update path (existing row, partial secret update)
 * - empty-state defaults (no config row exists)
 */
class SlackConfigServiceTest extends TestCase
{
    /**
     * @var array<string>
     */
    protected array $fixtures = [
        'app.Agents',
        'app.AgentSlackConfigs',
    ];

    private SlackConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SlackConfigService();
    }

    /**
     * Saving and immediately reading back config must return the saved values.
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
