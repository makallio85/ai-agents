<?php
declare(strict_types=1);

namespace App\Test\TestCase\Channels;

use App\Channels\MessageChannelInterface;
use App\Channels\MessageChannelRegistry;
use App\Channels\Slack\SlackMessageChannel;
use App\Channels\WhatsApp\WhatsAppMessageChannel;
use Cake\TestSuite\TestCase;

/**
 * Covers MessageChannelRegistry behaviour and the built-in channel set.
 *
 * The registry is the only place callers look up channel implementations
 * by key, so the tests assert that:
 * - the empty registry round-trips registration / lookup
 * - duplicate keys overwrite (last-registered wins)
 * - default() registers Slack and WhatsApp in deterministic order so the
 *   admin UI renders them consistently
 */
class MessageChannelRegistryTest extends TestCase
{
    public function testRegisterAndGetReturnsTheSameInstance(): void
    {
        $registry = new MessageChannelRegistry();
        $channel = new FakeMessageChannel('fake', 'Fake');

        $registry->register($channel);

        $this->assertSame($channel, $registry->get('fake'));
    }

    public function testGetReturnsNullForUnknownKey(): void
    {
        $registry = new MessageChannelRegistry();

        $this->assertNull($registry->get('does-not-exist'));
    }

    public function testRegisterReplacesExistingChannelOnSameKey(): void
    {
        $registry = new MessageChannelRegistry();
        $first = new FakeMessageChannel('fake', 'First');
        $second = new FakeMessageChannel('fake', 'Second');

        $registry->register($first);
        $registry->register($second);

        $this->assertSame($second, $registry->get('fake'));
        $this->assertCount(1, $registry->all());
    }

    public function testAllReturnsChannelsInInsertionOrder(): void
    {
        $registry = new MessageChannelRegistry();
        $registry->register(new FakeMessageChannel('a', 'A'));
        $registry->register(new FakeMessageChannel('b', 'B'));
        $registry->register(new FakeMessageChannel('c', 'C'));

        $this->assertSame(['a', 'b', 'c'], array_keys($registry->all()));
    }

    public function testDefaultRegistryWiresSlackAndWhatsapp(): void
    {
        $registry = MessageChannelRegistry::default();

        $this->assertInstanceOf(SlackMessageChannel::class, $registry->get('slack'));
        $this->assertInstanceOf(WhatsAppMessageChannel::class, $registry->get('whatsapp'));
        $this->assertSame(['slack', 'whatsapp'], array_keys($registry->all()));
    }
}

/**
 * Minimal MessageChannelInterface implementation used only in registry tests.
 * Keeps the test file self-contained — no fixtures, no DB, no Configure reads.
 */
final class FakeMessageChannel implements MessageChannelInterface
{
    public function __construct(private string $key, private string $label)
    {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function description(): string
    {
        return 'Fake channel for tests';
    }

    public function readForUi(int $agentId): array
    {
        return ['enabled' => false, 'agent_id' => $agentId];
    }

    public function setForAgent(int $agentId, array $data): array
    {
        return ['enabled' => (bool)($data['enabled'] ?? false), 'agent_id' => $agentId];
    }
}
