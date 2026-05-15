<?php
declare(strict_types=1);

namespace App\Test\TestCase\Channels\WhatsApp;

use App\Channels\WhatsApp\Service\WhatsAppConfigService;
use App\Channels\WhatsApp\WhatsAppMessageChannel;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

/**
 * Validates WhatsAppMessageChannel's wrapper-validation rules.
 *
 * Same intent as SlackMessageChannelTest: the channel must reject
 * incomplete admin payloads before they reach the database and must
 * preserve the existing access_token when the admin submits an empty
 * value on update.
 */
class WhatsAppMessageChannelTest extends TestCase
{
    /**
     * @var array<string>
     */
    protected array $fixtures = [
        'app.Agents',
        'app.AgentWhatsAppConfigs',
    ];

    private WhatsAppMessageChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channel = new WhatsAppMessageChannel(new WhatsAppConfigService());
    }

    public function testMetadata(): void
    {
        $this->assertSame('whatsapp', $this->channel->key());
        $this->assertSame('WhatsApp', $this->channel->label());
        $this->assertNotSame('', $this->channel->description());
    }

    public function testReadForUiReturnsEmptyShapeWhenNoConfig(): void
    {
        $payload = $this->channel->readForUi(1);

        $this->assertFalse($payload['access_token_set']);
        $this->assertFalse($payload['enabled']);
        $this->assertNull($payload['phone_number_id']);
    }

    public function testSetForAgentRoundtrips(): void
    {
        $payload = $this->channel->setForAgent(1, [
            'phone_number_id'       => '111222333',
            'display_number'        => '+358401111111',
            'access_token'          => 'EAA-test',
            'welcome_template_name' => 'agent_notice',
            'enabled'               => true,
        ]);

        $this->assertSame('111222333', $payload['phone_number_id']);
        $this->assertSame('+358401111111', $payload['display_number']);
        $this->assertTrue($payload['access_token_set']);
        $this->assertSame('agent_notice', $payload['welcome_template_name']);
        $this->assertTrue($payload['enabled']);
    }

    public function testSetForAgentRequiresPhoneNumberIdAndDisplayNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('phone_number_id and display_number are required');

        $this->channel->setForAgent(1, ['phone_number_id' => '', 'display_number' => '']);
    }

    public function testSetForAgentBlankTokenOnUpdateKeepsExistingValue(): void
    {
        $this->channel->setForAgent(1, [
            'phone_number_id' => '111222333',
            'display_number'  => '+358401111111',
            'access_token'    => 'EAA-test',
            'enabled'         => false,
        ]);

        $payload = $this->channel->setForAgent(1, [
            'phone_number_id' => '111222333',
            'display_number'  => '+358401111111',
            'access_token'    => '',
            'enabled'         => true,
        ]);

        $this->assertTrue($payload['access_token_set']);
        $this->assertTrue($payload['enabled']);
    }
}
