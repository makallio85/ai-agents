<?php
declare(strict_types=1);

namespace App\Test\TestCase\Messaging\Service;

use App\Messaging\Contract\MessageHandlerInterface;
use App\Messaging\Service\MessageHandlerRegistry;
use PHPUnit\Framework\TestCase;

class MessageHandlerRegistryTest extends TestCase
{
    public function testFallsBackToDefaultWhenPluginNotRegistered(): void
    {
        $default = $this->createStub(MessageHandlerInterface::class);
        $registry = new MessageHandlerRegistry($default);
        $this->assertSame($default, $registry->resolve('UnknownPlugin'));
        $this->assertSame($default, $registry->resolve(null));
    }

    public function testResolvesRegisteredHandler(): void
    {
        $default = $this->createStub(MessageHandlerInterface::class);
        $custom = $this->createStub(MessageHandlerInterface::class);
        $registry = new MessageHandlerRegistry($default);
        $registry->register('DevOpsOrchestrator', $custom);

        $this->assertSame($custom, $registry->resolve('DevOpsOrchestrator'));
        $this->assertSame($default, $registry->resolve('OtherPlugin'));
    }
}
