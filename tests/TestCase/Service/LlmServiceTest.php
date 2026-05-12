<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Integration\Llm\LlmClientFactory;
use App\Integration\Llm\LlmClientInterface;
use App\Integration\Llm\LlmMessage;
use App\Integration\Llm\LlmResponse;
use App\Model\Entity\Agent;
use App\Model\Entity\AgentContext;
use App\Service\AgentLogService;
use App\Service\LlmService;
use Cake\TestSuite\TestCase;
use RuntimeException;

/**
 * Unit tests for LlmService.
 *
 * LlmClientFactory and LlmClientInterface are mocked so no network calls
 * are made. Tests verify system prompt assembly, context injection, option
 * building, and logging behaviour.
 */
class LlmServiceTest extends TestCase
{
    private LlmService $service;
    private LlmClientInterface $clientMock;
    private LlmClientFactory $factoryMock;
    private AgentLogService $logMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientMock = $this->createMock(LlmClientInterface::class);
        $this->factoryMock = $this->createMock(LlmClientFactory::class);
        $this->logMock = $this->createMock(AgentLogService::class);

        $this->factoryMock->method('make')->willReturn($this->clientMock);

        $this->service = new LlmService($this->factoryMock, $this->logMock);
    }

    // ── assertProviderConfigured ──────────────────────────────────────────────

    public function testCompleteThrowsWhenNoProviderConfigured(): void
    {
        $agent = new Agent(['id' => 1, 'name' => 'TestAgent', 'llm_provider' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no LLM provider configured');

        $this->service->complete($agent, [], 'exec-1', null);
    }

    public function testStreamThrowsWhenNoProviderConfigured(): void
    {
        $agent = new Agent(['id' => 1, 'name' => 'TestAgent', 'llm_provider' => '']);

        $this->expectException(RuntimeException::class);
        $this->service->stream($agent, [], 'exec-1', null, function (string $d): void {});
    }

    // ── System prompt assembly ────────────────────────────────────────────────

    public function testCompletePrependsSystemMessageFromInstructions(): void
    {
        $agent = new Agent([
            'id' => 1,
            'name' => 'TestAgent',
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4o',
            'instructions' => 'You are a helpful assistant.',
            'agent_contexts' => [],
        ]);

        $capturedMessages = null;
        $this->clientMock->expects($this->once())
            ->method('complete')
            ->willReturnCallback(function (array $messages) use (&$capturedMessages): LlmResponse {
                $capturedMessages = $messages;
                return new LlmResponse('OK', 10, 'gpt-4o', 'stop');
            });

        $this->service->complete($agent, [], 'exec-1', null);

        $this->assertNotNull($capturedMessages);
        $this->assertEquals('system', $capturedMessages[0]->role);
        $this->assertStringContainsString('You are a helpful assistant.', $capturedMessages[0]->content);
    }

    public function testCompleteInjectsAgentContextIntoSystemPrompt(): void
    {
        $ctx = new AgentContext(['context_key' => 'repo', 'value' => 'my-org/my-repo']);
        $agent = new Agent([
            'id' => 1,
            'name' => 'TestAgent',
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4o',
            'instructions' => 'Base instructions.',
            'agent_contexts' => [$ctx],
        ]);

        $capturedMessages = null;
        $this->clientMock->expects($this->once())
            ->method('complete')
            ->willReturnCallback(function (array $messages) use (&$capturedMessages): LlmResponse {
                $capturedMessages = $messages;
                return new LlmResponse('OK', 10, 'gpt-4o', 'stop');
            });

        $this->service->complete($agent, [], 'exec-1', null);

        $systemContent = $capturedMessages[0]->content;
        $this->assertStringContainsString('repo: my-org/my-repo', $systemContent);
    }

    public function testCompleteAddsNoSystemMessageWhenNoInstructions(): void
    {
        $userMsg = new LlmMessage('user', 'Hello');
        $agent = new Agent([
            'id' => 1,
            'name' => 'TestAgent',
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4o',
            'instructions' => '',
            'agent_contexts' => [],
        ]);

        $capturedMessages = null;
        $this->clientMock->expects($this->once())
            ->method('complete')
            ->willReturnCallback(function (array $messages) use (&$capturedMessages): LlmResponse {
                $capturedMessages = $messages;
                return new LlmResponse('OK', 10, 'gpt-4o', 'stop');
            });

        $this->service->complete($agent, [$userMsg], 'exec-1', null);

        // First message should be the user message, no system injection
        $this->assertEquals('user', $capturedMessages[0]->role);
    }

    // ── Model option forwarding ───────────────────────────────────────────────

    public function testCompleteForwardsModelToClient(): void
    {
        $agent = new Agent([
            'id' => 1,
            'name' => 'TestAgent',
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4-turbo',
            'instructions' => null,
            'agent_contexts' => [],
        ]);

        $capturedOptions = null;
        $this->clientMock->expects($this->once())
            ->method('complete')
            ->willReturnCallback(function (array $msgs, array $opts) use (&$capturedOptions): LlmResponse {
                $capturedOptions = $opts;
                return new LlmResponse('OK', 5, 'gpt-4-turbo', 'stop');
            });

        $this->service->complete($agent, [], 'exec-1', null);

        $this->assertEquals('gpt-4-turbo', $capturedOptions['model']);
    }

    // ── Factory delegation ────────────────────────────────────────────────────

    public function testCompleteUsesCorrectProviderFromFactory(): void
    {
        $agent = new Agent([
            'id' => 1,
            'name' => 'A',
            'llm_provider' => 'anthropic',
            'llm_model' => 'claude-sonnet-4-6',
            'instructions' => null,
            'agent_contexts' => [],
        ]);

        $this->factoryMock->expects($this->once())
            ->method('make')
            ->with('anthropic')
            ->willReturn($this->clientMock);

        $this->clientMock->method('complete')
            ->willReturn(new LlmResponse('Hi', 5, 'claude-sonnet-4-6', 'end_turn'));

        $this->service->complete($agent, [], 'exec-1', null);
    }

    // ── Streaming ─────────────────────────────────────────────────────────────

    public function testStreamInvokesOnChunkCallback(): void
    {
        $agent = new Agent([
            'id' => 1,
            'name' => 'TestAgent',
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4o',
            'instructions' => null,
            'agent_contexts' => [],
        ]);

        $chunks = [];
        $this->clientMock->expects($this->once())
            ->method('stream')
            ->willReturnCallback(function (array $msgs, array $opts, callable $cb) use (&$chunks): LlmResponse {
                $cb('Hello');
                $cb(' world');
                $chunks[] = 'Hello';
                $chunks[] = ' world';
                return new LlmResponse('Hello world', null, 'gpt-4o', 'stop');
            });

        $received = [];
        $response = $this->service->stream($agent, [], 'exec-1', null, function (string $d) use (&$received): void {
            $received[] = $d;
        });

        $this->assertEquals(['Hello', ' world'], $received);
        $this->assertEquals('Hello world', $response->content);
    }

    // ── Logging ───────────────────────────────────────────────────────────────

    public function testCompleteLogsSuccessOnFinish(): void
    {
        $agent = new Agent([
            'id' => 5,
            'name' => 'TestAgent',
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4o',
            'instructions' => null,
            'agent_contexts' => [],
        ]);

        $this->clientMock->method('complete')
            ->willReturn(new LlmResponse('Done', 20, 'gpt-4o', 'stop'));

        $this->logMock->expects($this->once())->method('success');

        $this->service->complete($agent, [], 'exec-1', null);
    }

    public function testCompleteLogsErrorOnException(): void
    {
        $agent = new Agent([
            'id' => 5,
            'name' => 'TestAgent',
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4o',
            'instructions' => null,
            'agent_contexts' => [],
        ]);

        $this->clientMock->method('complete')
            ->willThrowException(new RuntimeException('API key invalid'));

        $this->logMock->expects($this->once())->method('error');

        $this->expectException(RuntimeException::class);
        $this->service->complete($agent, [], 'exec-1', null);
    }
}
