<?php
declare(strict_types=1);

namespace App\Test\TestCase\Messaging\Service;

use App\Messaging\Service\MessageDispatcher;
use App\Model\Entity\ChatMessage;
use App\Model\Entity\ChatSession;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * Verifies MessageDispatcher::reply() correctly propagates the triggering
 * inbound message's thread id into the outbound ChatMessage's metadata.
 *
 * Why this matters: SlackTransport::send() is called asynchronously from
 * SendMessageJob. By the time the job runs, a second inbound may have arrived
 * from the user. Without storing the inbound_thread_id at reply-creation time,
 * resolveThreadTs() would query for the *latest* inbound and pick the wrong
 * thread, causing the agent's reply to appear in a different thread than the
 * message that triggered it.
 */
class MessageDispatcherTest extends TestCase
{
    protected array $fixtures = [
        'app.Roles',
        'app.Users',
        'app.Agents',
        'app.ChatSessions',
        'app.ChatMessages',
    ];

    private MessageDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new MessageDispatcher();
    }

    /**
     * When reply() is called with a triggering inbound ChatMessage that has
     * an external_thread_id, the created outbound must carry that thread id
     * in its metadata under the key 'inbound_thread_id' so SendMessageJob can
     * pass it to SlackTransport without re-querying the DB.
     */
    public function testReplyStoresInboundThreadIdInMetadata(): void
    {
        $sessions = TableRegistry::getTableLocator()->get('ChatSessions');
        /** @var ChatSession $session */
        $session = $sessions->newEntity([
            'user_id' => 1,
            'agent_id' => 1,
            'channel' => 'web', // web avoids queue push so test stays isolated
            'title' => 'Thread-id test session',
        ]);
        $sessions->saveOrFail($session);

        $messages = TableRegistry::getTableLocator()->get('ChatMessages');
        /** @var ChatMessage $inbound */
        $inbound = $messages->newEntity([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_USER,
            'channel' => 'web',
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'Hello, what can you do?',
            'content_type' => ChatMessage::CONTENT_TEXT,
            'external_thread_id' => '1700000001.000200',
            'status' => ChatMessage::STATUS_RECEIVED,
        ]);
        $messages->saveOrFail($inbound);

        $outbound = $this->dispatcher->reply($session, 'I can help.', $inbound);

        $meta = json_decode((string)$outbound->metadata, true);
        $this->assertIsArray($meta, 'Outbound metadata must be a JSON object');
        $this->assertArrayHasKey('inbound_thread_id', $meta, 'Outbound metadata must carry inbound_thread_id');
        $this->assertSame('1700000001.000200', $meta['inbound_thread_id']);
    }

    /**
     * When reply() is called without a triggering inbound (or with an inbound
     * that has no external_thread_id), the outbound metadata must NOT contain
     * an inbound_thread_id key so that SlackTransport can fall back to its
     * DB query for legacy rows.
     */
    public function testReplyWithoutTriggeringInboundDoesNotStoreThreadId(): void
    {
        $sessions = TableRegistry::getTableLocator()->get('ChatSessions');
        /** @var ChatSession $session */
        $session = $sessions->newEntity([
            'user_id' => 1,
            'agent_id' => 1,
            'channel' => 'web',
            'title' => 'No-thread-id test session',
        ]);
        $sessions->saveOrFail($session);

        $outbound = $this->dispatcher->reply($session, 'Hello!');

        // Metadata is null when there's nothing to store.
        $meta = $outbound->metadata !== null
            ? json_decode((string)$outbound->metadata, true)
            : [];
        $this->assertArrayNotHasKey(
            'inbound_thread_id',
            $meta ?? [],
            'inbound_thread_id must not appear when no triggering inbound is given'
        );
    }
}
