<?php
declare(strict_types=1);

namespace App\Test\TestCase\Channels\Slack;

use App\Channels\Slack\Service\SlackAgentConfig;
use App\Channels\Slack\Service\SlackConfigService;
use App\Channels\Slack\Service\SlackOnboardingService;
use App\Channels\Slack\SlackClientInterface;
use App\Channels\Slack\SlackTransport;
use App\Messaging\Dto\OutboundMessage;
use App\Model\Entity\Agent;
use App\Model\Entity\ChatMessage;
use App\Model\Entity\ChatSession;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * Verifies that SlackTransport::send() uses the outbound message's stored
 * inbound_thread_id (from metadata) rather than re-querying the DB for the
 * latest inbound's thread_ts.
 *
 * Race condition being tested:
 *   1. User sends Message A  → agent loop starts
 *   2. User sends Message B  → arrives in DB while A's loop is still running
 *   3. Agent's reply to A is dispatched via SendMessageJob
 *   4. Bug: resolveThreadTs() picks up B's thread_ts → reply appears in B's thread
 *   5. Fix: reply stores inbound_thread_id at creation time → send() uses it
 */
class SlackTransportSendTest extends TestCase
{
    protected array $fixtures = [
        'app.Roles',
        'app.Users',
        'app.Agents',
        'app.ChatSessions',
        'app.ChatMessages',
    ];

    /**
     * Before the fix, resolveThreadTs() queries for the latest inbound and
     * returns B's thread_ts. After the fix it reads inbound_thread_id from the
     * outbound message's metadata and returns A's thread_ts.
     *
     * The mock client assertion catches which value was actually used.
     */
    public function testSendUsesInboundThreadIdFromOutboundMetadataNotLatestInbound(): void
    {
        // ── Arrange ──────────────────────────────────────────────────────────

        // Create a Slack session linked to agent 1 (from fixture).
        $sessions = TableRegistry::getTableLocator()->get('ChatSessions');
        /** @var ChatSession $session */
        $session = $sessions->newEntity([
            'user_id' => 1,
            'agent_id' => 1,
            'channel' => 'slack',
            'title' => 'Race condition session',
        ]);
        $sessions->saveOrFail($session);

        $messages = TableRegistry::getTableLocator()->get('ChatMessages');

        // Inbound A — the message that triggered the agent loop.
        // thread_ts = 'A_TS'; this is what the reply should be sent into.
        $messages->saveOrFail($messages->newEntity([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_USER,
            'channel' => 'slack',
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'First message (A)',
            'content_type' => ChatMessage::CONTENT_TEXT,
            'external_message_id' => 'T0:C0CH:1700000001.000100',
            'external_thread_id' => '1700000001.000100',
            'status' => ChatMessage::STATUS_RECEIVED,
            'metadata' => json_encode(['slack_channel_id' => 'C0CH']),
            'created' => '2026-01-10 10:00:01',
            'modified' => '2026-01-10 10:00:01',
        ]));

        // Inbound B — arrived AFTER A was queued but BEFORE its reply was sent.
        // thread_ts = 'B_TS'; the bug causes the reply to A to land here.
        $messages->saveOrFail($messages->newEntity([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_USER,
            'channel' => 'slack',
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'Second message (B) — arrives during A processing',
            'content_type' => ChatMessage::CONTENT_TEXT,
            'external_message_id' => 'T0:C0CH:1700000002.000200',
            'external_thread_id' => '1700000002.000200',
            'status' => ChatMessage::STATUS_RECEIVED,
            'metadata' => json_encode(['slack_channel_id' => 'C0CH']),
            'created' => '2026-01-10 10:00:02',
            'modified' => '2026-01-10 10:00:02',
        ]));

        // Mock Slack client: assert postMessage is called with A's thread_ts.
        $client = $this->createMock(SlackClientInterface::class);
        $client->expects($this->once())
            ->method('postMessage')
            ->with(
                'xoxb-test-token',          // bot token
                'C0CH',                      // slack channel id (from latest inbound metadata)
                'Reply to A',                // message body
                '1700000001.000100',         // ← MUST be A's thread_ts, not B's
            )
            ->willReturn(['ts' => '1700000099.000001', 'channel' => 'C0CH', 'message' => ['thread_ts' => '1700000001.000100']]);

        $agent = new Agent(['id' => 1, 'name' => 'Test Agent']);
        $config = new SlackAgentConfig(
            agent: $agent,
            appId: 'A0APPID',
            botUserId: 'U0BOT',
            botToken: 'xoxb-test-token',
            signingSecret: 'secret',
            teamId: 'T0TEAM',
            enabled: true,
        );

        $configService = $this->createStub(SlackConfigService::class);
        $configService->method('findConfigByAgentId')->willReturn($config);

        $onboarding = $this->createStub(SlackOnboardingService::class);
        $transport = new SlackTransport($client, $configService, $onboarding);

        // The outbound message carries inbound_thread_id = A's ts, set at
        // reply-creation time by MessageDispatcher::reply($session, $text, $inboundA).
        $outboundPayload = new OutboundMessage(
            'Reply to A',
            OutboundMessage::CONTENT_TEXT,
            ['inbound_thread_id' => '1700000001.000100'],  // set at reply() time
        );

        // ── Act ──────────────────────────────────────────────────────────────
        $transport->send($session, $outboundPayload);

        // PHPUnit verifies the mock expectation automatically on tearDown.
        // If postMessage was called with '1700000002.000200' (B's ts), the
        // expectation fails — capturing the race-condition bug.
    }
}
