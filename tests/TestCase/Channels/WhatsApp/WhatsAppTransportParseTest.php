<?php
declare(strict_types=1);

namespace App\Test\TestCase\Channels\WhatsApp;

use App\Channels\WhatsApp\Service\WhatsAppConfigService;
use App\Channels\WhatsApp\Service\WhatsAppOnboardingService;
use App\Channels\WhatsApp\WhatsAppClientInterface;
use App\Channels\WhatsApp\WhatsAppTransport;
use App\Messaging\Dto\InboundEnvelope;
use App\Model\Entity\ChatMessage;
use App\Model\Entity\InboundEvent;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that the transport correctly translates Meta's webhook payload
 * shape into channel-agnostic InboundEnvelope DTOs. Pure unit test —
 * no DB, no network.
 */
class WhatsAppTransportParseTest extends TestCase
{
    private WhatsAppTransport $transport;

    protected function setUp(): void
    {
        $client = $this->createStub(WhatsAppClientInterface::class);
        $configService = $this->createStub(WhatsAppConfigService::class);
        $onboarding = $this->createStub(WhatsAppOnboardingService::class);
        $this->transport = new WhatsAppTransport($client, $configService, $onboarding);
    }

    public function testParsesTextMessage(): void
    {
        $event = $this->event([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => '111'],
                        'messages' => [[
                            'id' => 'wamid.ABC',
                            'from' => '358401234567',
                            'type' => 'text',
                            'text' => ['body' => 'Hello agent'],
                        ]],
                    ],
                ]],
            ]],
        ]);

        $envelopes = $this->transport->parseInbound($event);
        $this->assertCount(1, $envelopes);
        $env = $envelopes[0];
        $this->assertSame(InboundEnvelope::KIND_MESSAGE, $env->kind);
        $this->assertSame('111', $env->externalAccountId);
        $this->assertSame('+358401234567', $env->externalIdentifier);
        $this->assertSame('wamid.ABC', $env->externalMessageId);
        $this->assertSame('Hello agent', $env->body);
        $this->assertSame(ChatMessage::CONTENT_TEXT, $env->contentType);
    }

    public function testParsesStatusUpdate(): void
    {
        $event = $this->event([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => '111'],
                        'statuses' => [[
                            'id' => 'wamid.OUT',
                            'recipient_id' => '358401234567',
                            'status' => 'delivered',
                        ]],
                    ],
                ]],
            ]],
        ]);

        $envelopes = $this->transport->parseInbound($event);
        $this->assertCount(1, $envelopes);
        $env = $envelopes[0];
        $this->assertSame(InboundEnvelope::KIND_STATUS, $env->kind);
        $this->assertSame(ChatMessage::STATUS_DELIVERED, $env->statusUpdate);
        $this->assertSame('wamid.OUT', $env->externalMessageId);
    }

    public function testParsesInteractiveButtonReply(): void
    {
        $event = $this->event([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => '111'],
                        'messages' => [[
                            'id' => 'wamid.BTN',
                            'from' => '15551234567',
                            'type' => 'interactive',
                            'interactive' => [
                                'button_reply' => ['title' => 'Yes please'],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ]);
        $envelopes = $this->transport->parseInbound($event);
        $this->assertSame('Yes please', $envelopes[0]->body);
    }

    public function testIgnoresEntriesWithoutPhoneNumberId(): void
    {
        $event = $this->event([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => [],
                        'messages' => [['id' => 'x', 'from' => '1', 'type' => 'text', 'text' => ['body' => 'hi']]],
                    ],
                ]],
            ]],
        ]);
        $this->assertCount(0, $this->transport->parseInbound($event));
    }

    /** @param array<string, mixed> $payload */
    private function event(array $payload): InboundEvent
    {
        $event = new InboundEvent();
        $event->channel = 'whatsapp';
        $event->payload = json_encode($payload);
        return $event;
    }
}
