<?php
declare(strict_types=1);

namespace App\Test\TestCase\Messaging\Service;

use App\Messaging\Contract\ChannelTransportInterface;
use App\Messaging\Exception\UnknownChannelException;
use App\Messaging\Service\ChannelRegistry;
use PHPUnit\Framework\TestCase;

class ChannelRegistryTest extends TestCase
{
    public function testRegisterAndGet(): void
    {
        $registry = new ChannelRegistry();
        $transport = $this->createStub(ChannelTransportInterface::class);
        $transport->method('name')->willReturn('whatsapp');

        $registry->register($transport);

        $this->assertTrue($registry->has('whatsapp'));
        $this->assertSame($transport, $registry->get('whatsapp'));
    }

    public function testUnknownChannelThrows(): void
    {
        $registry = new ChannelRegistry();
        $this->expectException(UnknownChannelException::class);
        $registry->get('email');
    }

    public function testHasReturnsFalseForUnknown(): void
    {
        $this->assertFalse((new ChannelRegistry())->has('telegram'));
    }
}
