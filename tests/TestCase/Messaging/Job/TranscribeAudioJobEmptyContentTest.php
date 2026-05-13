<?php
declare(strict_types=1);

namespace App\Test\TestCase\Messaging\Job;

use App\Messaging\Job\TranscribeAudioJob;
use App\Messaging\Service\ChannelRegistry;
use App\Messaging\Service\InboundDispatchService;
use App\Messaging\Service\MessageDispatcher;
use App\Messaging\Contract\ChannelTransportInterface;
use App\Integration\Speech\SpeechToTextInterface;
use App\Model\Entity\ChatMessage;
use App\Service\AgentLogService;
use Cake\ORM\TableRegistry;
use Cake\Queue\Job\Message;
use Cake\TestSuite\TestCase;
use Interop\Queue\Processor;

/**
 * Regression test: TranscribeAudioJob must treat empty downloaded audio
 * content as a graceful failure rather than forwarding garbage bytes to
 * the Whisper API.
 *
 * Root cause: SlackClient::downloadFile() used file_get_contents() with
 * follow_location which can silently return an HTML redirect page body
 * (instead of audio bytes) when the Slack CDN redirects in PHP CLI mode
 * (the queue worker context). The bytes are non-empty but not valid audio,
 * causing Whisper to return 400 "Invalid file format" every time.
 *
 * The fix adds an explicit empty-content guard in execute() before the
 * transcribe() call, and switches downloadFile() to curl (which handles
 * redirects correctly in CLI mode).
 *
 * This test validates the guard: a transport that returns an empty content
 * string must trigger failGracefully() with error_code='media_download_failed',
 * not reach the SpeechToTextInterface.
 */
class TranscribeAudioJobEmptyContentTest extends TestCase
{
    protected array $fixtures = [
        'app.Roles',
        'app.Users',
        'app.Agents',
        'app.AgentContexts',
        'app.ChatSessions',
        'app.ChatMessages',
    ];

    /**
     * When the channel transport returns empty audio content, execute() must
     * mark the message as failed with error_code='media_download_failed' and
     * return ACK (not REQUEUE — there is nothing to retry).
     */
    public function testEmptyAudioContentTriggersGracefulFailure(): void
    {
        // ── Arrange: create a Slack audio message in the DB ──────────────────

        /** @var \App\Model\Table\ChatSessionsTable $sessions */
        $sessions = TableRegistry::getTableLocator()->get('ChatSessions');
        $session = $sessions->newEntity([
            'user_id' => 1,
            'agent_id' => 1,
            'channel' => 'slack',
            'title' => 'Audio test session',
        ]);
        $sessions->saveOrFail($session);

        /** @var \App\Model\Table\ChatMessagesTable $messages */
        $messages = TableRegistry::getTableLocator()->get('ChatMessages');
        /** @var ChatMessage $audioMsg */
        $audioMsg = $messages->newEntity([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_USER,
            'channel' => 'slack',
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => '[audio]',
            'content_type' => ChatMessage::CONTENT_AUDIO,
            'media_url' => 'https://files.slack.com/files-pri/T0TEAM/F001/voice.m4a',
            'media_mime_type' => 'audio/mp4',
            'status' => ChatMessage::STATUS_QUEUED,
        ]);
        $messages->saveOrFail($audioMsg);

        // ── Mock the channel transport to return empty bytes ─────────────────

        $transport = $this->createMock(ChannelTransportInterface::class);
        $transport->method('name')->willReturn('slack');
        // Returns empty content — simulates file_get_contents returning an
        // HTML redirect page that php maps to a non-false but invalid body.
        $transport->method('fetchMedia')->willReturn(['content' => '', 'mime' => 'audio/mp4']);

        $registry = new ChannelRegistry();
        $registry->register($transport);

        // Speech must NOT be called when content is empty.
        $speech = $this->createMock(SpeechToTextInterface::class);
        $speech->expects($this->never())->method('transcribe');

        // MessageDispatcher::sendSystem() should be called to surface an apology.
        $dispatcher = $this->createMock(MessageDispatcher::class);
        $dispatcher->expects($this->once())->method('sendSystem');

        $dispatch = $this->createMock(InboundDispatchService::class);
        $dispatch->expects($this->never())->method('route');

        $logService = $this->createMock(AgentLogService::class);

        $job = new TranscribeAudioJob($registry, $speech, $dispatch, $dispatcher, $logService);

        // ── Build a minimal queue Message with the message_id argument ────────

        $queueMessage = $this->createMock(Message::class);
        $queueMessage->method('getArgument')->willReturnCallback(
            fn(string $key, mixed $default = null) => $key === 'message_id' ? $audioMsg->id : $default
        );

        // ── Act ───────────────────────────────────────────────────────────────

        $result = $job->execute($queueMessage);

        // ── Assert ────────────────────────────────────────────────────────────

        $this->assertSame(Processor::ACK, $result);

        /** @var ChatMessage $reloaded */
        $reloaded = $messages->find()->where(['id' => $audioMsg->id])->firstOrFail();
        $this->assertSame(ChatMessage::STATUS_FAILED, $reloaded->status);
        $this->assertSame('media_download_failed', $reloaded->error_code);
    }
}
