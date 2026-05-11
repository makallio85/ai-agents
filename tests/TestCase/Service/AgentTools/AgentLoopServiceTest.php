<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\AgentTools;

use App\Integration\Llm\LlmMessage;
use App\Integration\Llm\OpenAiClient;
use App\Integration\Llm\Tool\ToolCall;
use App\Integration\Llm\Tool\ToolCallResponse;
use App\Service\AgentTools\AgentLoopService;
use App\Service\AgentTools\GitHubToolProvider;
use Cake\TestSuite\TestCase;
use RuntimeException;

/**
 * Unit tests for AgentLoopService (ReAct loop).
 *
 * OpenAiClient and GitHubToolProvider are mocked so no real HTTP calls happen.
 * Tests verify the loop's termination logic, SSE event emission, tool dispatch
 * routing, and the MAX_ITERATIONS guard.
 */
class AgentLoopServiceTest extends TestCase
{
    private OpenAiClient $client;
    private GitHubToolProvider $toolProvider;
    private AgentLoopService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(OpenAiClient::class);
        $this->toolProvider = $this->createMock(GitHubToolProvider::class);
        $this->service = new AgentLoopService($this->client, $this->toolProvider);
    }

    private function makeMessages(): array
    {
        return [new LlmMessage('user', 'List my GitHub repos.')];
    }

    // ── Immediate final answer (no tool calls) ────────────────────────────────

    public function testRunReturnsFinalAnswerImmediatelyWhenNoDone(): void
    {
        $finalResponse = new ToolCallResponse(
            content: 'Here are your repos: acme/app',
            toolCalls: [],
            model: 'gpt-4o',
            tokensUsed: 20,
        );

        $this->toolProvider->method('getDefinitions')->willReturn([]);
        $this->client->expects($this->once())
            ->method('completeWithTools')
            ->willReturn($finalResponse);

        $events = [];
        $result = $this->service->run($this->makeMessages(), [], function (string $e) use (&$events) {
            $events[] = json_decode($e, true);
        });

        $this->assertEquals('Here are your repos: acme/app', $result->content);
        $this->assertEquals('stop', $result->finishReason);
        $this->assertCount(1, $events);
        $this->assertEquals('chunk', $events[0]['type']);
        $this->assertEquals('Here are your repos: acme/app', $events[0]['content']);
    }

    // ── One tool call then final answer ───────────────────────────────────────

    public function testRunExecutesToolAndLoopsToFinalAnswer(): void
    {
        $toolCall = new ToolCall(id: 'call_1', name: 'github_list_repos', arguments: []);

        $toolCallResponse = new ToolCallResponse(
            content: '',
            toolCalls: [$toolCall],
            model: 'gpt-4o',
            tokensUsed: 10,
        );

        $finalResponse = new ToolCallResponse(
            content: 'You have 2 repos.',
            toolCalls: [],
            model: 'gpt-4o',
            tokensUsed: 15,
        );

        $this->toolProvider->method('getDefinitions')->willReturn([]);
        $this->toolProvider->expects($this->once())
            ->method('dispatch')
            ->with('github_list_repos', [])
            ->willReturn('acme/app, acme/infra');

        $this->client->expects($this->exactly(2))
            ->method('completeWithTools')
            ->willReturnOnConsecutiveCalls($toolCallResponse, $finalResponse);

        $events = [];
        $result = $this->service->run($this->makeMessages(), [], function (string $e) use (&$events) {
            $events[] = json_decode($e, true);
        });

        $this->assertEquals('You have 2 repos.', $result->content);

        $types = array_column($events, 'type');
        $this->assertContains('tool_call', $types);
        $this->assertContains('tool_result', $types);
        $this->assertContains('chunk', $types);
    }

    // ── SSE event shapes ──────────────────────────────────────────────────────

    public function testToolCallEventContainsToolNameAndArgs(): void
    {
        $toolCall = new ToolCall(id: 'call_x', name: 'github_get_file', arguments: ['owner' => 'acme', 'repo' => 'app', 'path' => 'README.md']);

        $toolCallResponse = new ToolCallResponse(content: '', toolCalls: [$toolCall], model: 'gpt-4o', tokensUsed: 5);
        $finalResponse = new ToolCallResponse(content: 'Done.', toolCalls: [], model: 'gpt-4o', tokensUsed: 8);

        $this->toolProvider->method('getDefinitions')->willReturn([]);
        $this->toolProvider->method('dispatch')->willReturn('sha:abc\n\n# Readme');

        $this->client->method('completeWithTools')
            ->willReturnOnConsecutiveCalls($toolCallResponse, $finalResponse);

        $events = [];
        $this->service->run($this->makeMessages(), [], function (string $e) use (&$events) {
            $events[] = json_decode($e, true);
        });

        $toolCallEvent = array_values(array_filter($events, fn($e) => $e['type'] === 'tool_call'))[0];
        $this->assertEquals('github_get_file', $toolCallEvent['tool']);
        $this->assertEquals(['owner' => 'acme', 'repo' => 'app', 'path' => 'README.md'], $toolCallEvent['args']);
    }

    // ── Tool failure is caught and fed back to LLM ───────────────────────────

    public function testToolDispatchExceptionIsCaughtAndResultReturnedAsErrorString(): void
    {
        $toolCall = new ToolCall(id: 'call_err', name: 'github_list_repos', arguments: []);
        $toolCallResponse = new ToolCallResponse(content: '', toolCalls: [$toolCall], model: 'gpt-4o', tokensUsed: 5);
        $finalResponse = new ToolCallResponse(content: 'Could not list repos.', toolCalls: [], model: 'gpt-4o', tokensUsed: 8);

        $this->toolProvider->method('getDefinitions')->willReturn([]);
        $this->toolProvider->method('dispatch')->willThrowException(new \RuntimeException('Network error'));

        $this->client->method('completeWithTools')
            ->willReturnOnConsecutiveCalls($toolCallResponse, $finalResponse);

        // Must not throw — error is turned into a tool result message
        $result = $this->service->run($this->makeMessages(), [], fn($e) => null);

        $this->assertEquals('Could not list repos.', $result->content);
    }

    // ── MAX_ITERATIONS guard ──────────────────────────────────────────────────

    public function testRunThrowsAfterMaxIterationsWithoutFinalAnswer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/maximum iterations/');

        $toolCall = new ToolCall(id: 'call_inf', name: 'github_list_repos', arguments: []);
        $infiniteToolCallResponse = new ToolCallResponse(content: '', toolCalls: [$toolCall], model: 'gpt-4o', tokensUsed: 5);

        $this->toolProvider->method('getDefinitions')->willReturn([]);
        $this->toolProvider->method('dispatch')->willReturn('some result');

        // Always return a tool call response → never isDone()
        $this->client->method('completeWithTools')->willReturn($infiniteToolCallResponse);

        $this->service->run($this->makeMessages(), [], fn($e) => null);
    }

    // ── LlmResponse shape ─────────────────────────────────────────────────────

    public function testRunReturnsLlmResponseWithCorrectModelAndTokens(): void
    {
        $finalResponse = new ToolCallResponse(
            content: 'Answer.',
            toolCalls: [],
            model: 'gpt-4o-mini',
            tokensUsed: 77,
        );

        $this->toolProvider->method('getDefinitions')->willReturn([]);
        $this->client->method('completeWithTools')->willReturn($finalResponse);

        $result = $this->service->run($this->makeMessages(), [], fn($e) => null);

        $this->assertEquals('gpt-4o-mini', $result->model);
        $this->assertEquals(77, $result->tokensUsed);
    }
}
