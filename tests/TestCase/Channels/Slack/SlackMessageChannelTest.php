<?php
declare(strict_types=1);

namespace App\Test\TestCase\Channels\Slack;

use App\Channels\Slack\Service\SlackConfigService;
use App\Channels\Slack\SlackMessageChannel;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

/**
 * Validates SlackMessageChannel's wrapper-validation rules.
 *
 * The channel's job is to translate the unified MessageChannelInterface
 * contract into the type-specific SlackConfigService API and reject
 * incomplete admin-form payloads with InvalidArgumentException before
 * touching the database.
 */
class SlackMessageChannelTest extends TestCase
{
    /**
     * @var array<string>
     */
    protected array $fixtures = [
        'app.Agents',
        'app.AgentSlackConfigs',
    ];

    private SlackMessageChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channel = new SlackMessageChannel(new SlackConfigService());
    }

    public function testMetadata(): void
    {
        $this->assertSame('slack', $this->channel->key());
        $this->assertSame('Slack', $this->channel->label());
        $this->assertNotSame('', $this->channel->description());
    }

    public function testReadForUiReturnsEmptyShapeWhenNoConfig(): void
    {
        $payload = $this->channel->readForUi(1);

        $this->assertFalse($payload['bot_token_set']);
        $this->assertFalse($payload['signing_secret_set']);
        $this->assertFalse($payload['enabled']);
        $this->assertNull($payload['app_id']);
    }

    public function testSetForAgentRoundtrips(): void
    {
        $payload = $this->channel->setForAgent(1, [
            'app_id'         => 'A0123TEST',
            'bot_user_id'    => 'U0123TEST',
            'bot_token'      => 'xoxb-test',
            'signing_secret' => 'sig-test',
            'team_id'        => 'T0123TEST',
            'enabled'        => true,
        ]);

        $this->assertSame('A0123TEST', $payload['app_id']);
        $this->assertSame('U0123TEST', $payload['bot_user_id']);
        $this->assertTrue($payload['bot_token_set']);
        $this->assertTrue($payload['signing_secret_set']);
        $this->assertTrue($payload['enabled']);
    }

    public function testSetForAgentRequiresAppIdAndBotUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('app_id and bot_user_id are required');

        $this->channel->setForAgent(1, ['app_id' => '', 'bot_user_id' => '']);
    }

    public function testSetForAgentRequiresBotTokenOnFirstSave(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bot_token is required on first save');

        $this->channel->setForAgent(1, [
            'app_id'         => 'A0123TEST',
            'bot_user_id'    => 'U0123TEST',
            'bot_token'      => '',
            'signing_secret' => 'sig-test',
        ]);
    }

    public function testSetForAgentRequiresSigningSecretOnFirstSave(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('signing_secret is required on first save');

        $this->channel->setForAgent(1, [
            'app_id'         => 'A0123TEST',
            'bot_user_id'    => 'U0123TEST',
            'bot_token'      => 'xoxb-test',
            'signing_secret' => '',
        ]);
    }

    public function testSetForAgentSecondCallWithBlankSecretsKeepsExistingValues(): void
    {
        $this->channel->setForAgent(1, [
            'app_id'         => 'A0123TEST',
            'bot_user_id'    => 'U0123TEST',
            'bot_token'      => 'xoxb-test',
            'signing_secret' => 'sig-test',
            'enabled'        => false,
        ]);

        $payload = $this->channel->setForAgent(1, [
            'app_id'         => 'A0123UPDATED',
            'bot_user_id'    => 'U0123UPDATED',
            'bot_token'      => '',
            'signing_secret' => '',
            'enabled'        => true,
        ]);

        $this->assertSame('A0123UPDATED', $payload['app_id']);
        $this->assertSame('U0123UPDATED', $payload['bot_user_id']);
        $this->assertTrue($payload['bot_token_set']);
        $this->assertTrue($payload['signing_secret_set']);
        $this->assertTrue($payload['enabled']);
    }
}
