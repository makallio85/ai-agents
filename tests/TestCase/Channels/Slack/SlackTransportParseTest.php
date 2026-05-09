<?php
declare(strict_types=1);

namespace App\Test\TestCase\Channels\Slack;

use App\Channels\Slack\Service\SlackConfigService;
use App\Channels\Slack\Service\SlackOnboardingService;
use App\Channels\Slack\SlackClientInterface;
use App\Channels\Slack\SlackTransport;
use App\Messaging\Dto\InboundEnvelope;
use App\Model\Entity\InboundEvent;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the transport translates Slack Events API payloads into the
 * channel-agnostic InboundEnvelope shape. Pure unit test — no DB, no
 * network.
 */
class SlackTransportParseTest extends TestCase
{
    private SlackTransport $transport;

    protected function setUp(): void
    {
        $client = $this->createStub(SlackClientInterface::class);
        $configService = $this->createStub(SlackConfigService::class);
        $onboarding = $this->createStub(SlackOnboardingService::class);
        $this->transport = new SlackTransport($client, $configService, $onboarding);
    }

    public function testParsesPlainMessage(): void
    {
        $event = $this->event([
            'type' => 'event_callback',
            'api_app_id' => 'A0APPID',
            'team_id' => 'T0TEAM',
            'event_id' => 'Ev01',
            'event' => [
                'type' => 'message',
                'channel' => 'C0CH',
                'user' => 'U0USER',
                'text' => 'hi agent',
                'ts' => '1700000001.000200',
            ],
        ]);

        $envelopes = $this->transport->parseInbound($event);
        $this->assertCount(1, $envelopes);
        $env = $envelopes[0];
        $this->assertSame(InboundEnvelope::KIND_MESSAGE, $env->kind);
        $this->assertSame('A0APPID', $env->externalAccountId);
        $this->assertSame('U0USER', $env->externalIdentifier);
        $this->assertSame('T0TEAM:C0CH:1700000001.000200', $env->externalMessageId);
        $this->assertSame('hi agent', $env->body);
        // No thread_ts on the event — falls back to ts so replies thread on the original.
        $this->assertSame('1700000001.000200', $env->externalThreadId);
    }

    public function testThreadedReplyKeepsThreadTs(): void
    {
        $event = $this->event([
            'type' => 'event_callback',
            'api_app_id' => 'A0APPID',
            'team_id' => 'T0TEAM',
            'event' => [
                'type' => 'message',
                'channel' => 'C0CH',
                'user' => 'U0USER',
                'text' => 'reply in thread',
                'ts' => '1700000005.000800',
                'thread_ts' => '1700000001.000200',
            ],
        ]);

        $env = $this->transport->parseInbound($event)[0];
        $this->assertSame('1700000001.000200', $env->externalThreadId);
    }

    public function testParsesAppMention(): void
    {
        $event = $this->event([
            'type' => 'event_callback',
            'api_app_id' => 'A0APPID',
            'team_id' => 'T0TEAM',
            'event' => [
                'type' => 'app_mention',
                'channel' => 'C0CH',
                'user' => 'U0USER',
                'text' => '<@U0BOT> hello',
                'ts' => '1700000001.000300',
            ],
        ]);

        $env = $this->transport->parseInbound($event)[0];
        $this->assertSame(InboundEnvelope::KIND_MESSAGE, $env->kind);
        $this->assertSame('<@U0BOT> hello', $env->body);
    }

    public function testIgnoresBotMessages(): void
    {
        $event = $this->event([
            'type' => 'event_callback',
            'api_app_id' => 'A0APPID',
            'team_id' => 'T0TEAM',
            'event' => [
                'type' => 'message',
                'subtype' => 'bot_message',
                'bot_id' => 'B0BOT',
                'channel' => 'C0CH',
                'user' => 'U0USER',
                'text' => 'echo from a bot',
                'ts' => '1700000001.000400',
            ],
        ]);

        $this->assertCount(0, $this->transport->parseInbound($event));
    }

    public function testIgnoresUrlVerificationCallback(): void
    {
        // The webhook controller handles url_verification inline so the parser
        // never sees them, but defend against a future code path that queues one.
        $event = $this->event([
            'type' => 'url_verification',
            'challenge' => 'abc',
        ]);

        $this->assertCount(0, $this->transport->parseInbound($event));
    }

    public function testParsesAudioFileShare(): void
    {
        $event = $this->event([
            'type' => 'event_callback',
            'api_app_id' => 'A0APPID',
            'team_id' => 'T0TEAM',
            'event' => [
                'type' => 'message',
                'channel' => 'C0CH',
                'user' => 'U0USER',
                'text' => '',
                'ts' => '1700000007.000900',
                'files' => [[
                    'id' => 'F0AUDIO',
                    'mimetype' => 'audio/m4a',
                    'filetype' => 'm4a',
                    'url_private_download' => 'https://files.slack.com/F0AUDIO/download',
                    'url_private' => 'https://files.slack.com/F0AUDIO',
                ]],
            ],
        ]);

        $envelopes = $this->transport->parseInbound($event);
        $this->assertCount(1, $envelopes);
        $env = $envelopes[0];
        $this->assertSame(\App\Model\Entity\ChatMessage::CONTENT_AUDIO, $env->contentType);
        $this->assertSame('https://files.slack.com/F0AUDIO/download', $env->mediaUrl);
        $this->assertSame('audio/m4a', $env->mediaMimeType);
    }

    public function testIgnoresMissingApiAppId(): void
    {
        $event = $this->event([
            'type' => 'event_callback',
            'team_id' => 'T0TEAM',
            'event' => [
                'type' => 'message',
                'channel' => 'C0CH',
                'user' => 'U0USER',
                'text' => 'orphan',
                'ts' => '1700000001.000500',
            ],
        ]);

        $this->assertCount(0, $this->transport->parseInbound($event));
    }

    /** @param array<string, mixed> $payload */
    private function event(array $payload): InboundEvent
    {
        $event = new InboundEvent();
        $event->channel = 'slack';
        $event->payload = json_encode($payload);
        return $event;
    }
}
