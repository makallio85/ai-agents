<?php
declare(strict_types=1);

namespace App\Test\TestCase\Messaging\Dto;

use App\Messaging\Dto\OutboundMessage;
use PHPUnit\Framework\TestCase;

class OutboundMessageTest extends TestCase
{
    public function testTextFactory(): void
    {
        $msg = OutboundMessage::text('hello');
        $this->assertSame('hello', $msg->body);
        $this->assertSame(OutboundMessage::CONTENT_TEXT, $msg->contentType);
        $this->assertSame([], $msg->metadata);
    }

    public function testTemplateFactoryCarriesMetadata(): void
    {
        $msg = OutboundMessage::template('order_shipped', 'en_US', [
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => '#42']]],
        ]);
        $this->assertSame(OutboundMessage::CONTENT_TEMPLATE, $msg->contentType);
        $this->assertSame('order_shipped', $msg->body);
        $this->assertSame('order_shipped', $msg->metadata['template_name']);
        $this->assertSame('en_US', $msg->metadata['language']);
        $this->assertNotEmpty($msg->metadata['components']);
    }
}
