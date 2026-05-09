<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Integration\Llm\LlmMessage;
use App\Model\Entity\ChatSession;
use App\Service\ChatSessionService;
use Cake\TestSuite\TestCase;

/**
 * Unit tests for ChatSessionService.
 *
 * All tests run against the in-memory test database using fixtures.
 * No external API calls are made.
 */
class ChatSessionServiceTest extends TestCase
{
    /** @var array<string> */
    protected array $fixtures = [
        'app.Roles',
        'app.Users',
        'app.Agents',
        'app.ChatSessions',
        'app.ChatMessages',
    ];

    private ChatSessionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChatSessionService();
    }

    // ── findByUser ────────────────────────────────────────────────────────────

    public function testFindByUserReturnsSessionsForUser(): void
    {
        $results = $this->service->findByUser(1);
        $this->assertCount(2, $results);
    }

    public function testFindByUserReturnsEmptyForUnknownUser(): void
    {
        $results = $this->service->findByUser(999);
        $this->assertCount(0, $results);
    }

    public function testFindByUserOrdersNewestFirst(): void
    {
        $results = $this->service->findByUser(1);
        // Session 2 was created after session 1
        $this->assertEquals(2, $results[0]->id);
        $this->assertEquals(1, $results[1]->id);
    }

    // ── findById ──────────────────────────────────────────────────────────────

    public function testFindByIdReturnsSession(): void
    {
        $session = $this->service->findById(1);
        $this->assertNotNull($session);
        $this->assertEquals(1, $session->id);
        $this->assertEquals('First session', $session->title);
    }

    public function testFindByIdIncludesMessages(): void
    {
        $session = $this->service->findById(1);
        $this->assertNotNull($session);
        $this->assertCount(2, $session->chat_messages);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $this->assertNull($this->service->findById(999));
    }

    // ── create ────────────────────────────────────────────────────────────────

    public function testCreatePersistsSession(): void
    {
        $session = $this->service->create(1, 1, 'Test session');
        $this->assertInstanceOf(ChatSession::class, $session);
        $this->assertGreaterThan(0, $session->id);
        $this->assertEquals('Test session', $session->title);
        $this->assertEquals(1, $session->user_id);
        $this->assertEquals(1, $session->agent_id);
    }

    public function testCreateAllowsNullTitle(): void
    {
        $session = $this->service->create(1, 1);
        $this->assertNull($session->title);
    }

    // ── addMessage ────────────────────────────────────────────────────────────

    public function testAddMessagePersistsUserMessage(): void
    {
        $msg = $this->service->addMessage(1, 'user', 'Hello there');
        $this->assertGreaterThan(0, $msg->id);
        $this->assertEquals('user', $msg->role);
        $this->assertEquals('Hello there', $msg->content);
    }

    public function testAddMessagePersistsAssistantMessageWithMeta(): void
    {
        $msg = $this->service->addMessage(1, 'assistant', 'Hi!', 55, 'gpt-4o');
        $this->assertEquals('assistant', $msg->role);
        $this->assertEquals(55, $msg->tokens_used);
        $this->assertEquals('gpt-4o', $msg->model_used);
    }

    // ── buildMessageHistory ───────────────────────────────────────────────────

    public function testBuildMessageHistoryReturnsLlmMessages(): void
    {
        $session = $this->service->findById(1);
        $history = $this->service->buildMessageHistory($session);

        $this->assertCount(2, $history);
        $this->assertContainsOnlyInstancesOf(LlmMessage::class, $history);
    }

    public function testBuildMessageHistoryPreservesRolesAndContent(): void
    {
        $session = $this->service->findById(1);
        $history = $this->service->buildMessageHistory($session);

        $this->assertEquals('user', $history[0]->role);
        $this->assertEquals('Hello, what can you do?', $history[0]->content);
        $this->assertEquals('assistant', $history[1]->role);
        $this->assertEquals('I can help you manage DevOps tasks.', $history[1]->content);
    }

    public function testBuildMessageHistoryIsEmptyForNewSession(): void
    {
        $session = $this->service->findById(2);
        $history = $this->service->buildMessageHistory($session);
        $this->assertCount(0, $history);
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function testDeleteRemovesSession(): void
    {
        $session = $this->service->findById(1);
        $this->service->delete($session);
        $this->assertNull($this->service->findById(1));
    }

    // ── updateTitle ───────────────────────────────────────────────────────────

    public function testUpdateTitlePersistsTitle(): void
    {
        $session = $this->service->findById(2);
        $this->assertNull($session->title);

        $this->service->updateTitle($session, 'New title');
        $reloaded = $this->service->findById(2);
        $this->assertEquals('New title', $reloaded->title);
    }
}
