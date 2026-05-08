<?php
declare(strict_types=1);

namespace App\Service;

use App\Integration\Llm\LlmClientFactory;
use App\Integration\Llm\LlmMessage;
use App\Integration\Llm\LlmResponse;
use App\Model\Entity\Agent;
use Cake\ORM\TableRegistry;
use RuntimeException;

/**
 * Orchestrates LLM calls on behalf of a configured agent.
 *
 * This service is the single entry point for all LLM interactions in the
 * platform. It is provider-agnostic: it reads the agent's llm_provider and
 * llm_model fields, delegates to LlmClientFactory to obtain the correct
 * backend client, prepends the agent's system prompt and context, and logs
 * every call via AgentLogService.
 *
 * Callers should never instantiate LLM clients directly; always go through
 * this service so that logging, prompt injection, and context assembly are
 * applied consistently.
 */
class LlmService
{
    public function __construct(
        private readonly LlmClientFactory $clientFactory,
        private readonly AgentLogService $logService,
    ) {
    }

    /**
     * Calls the LLM for the given agent with a streaming response.
     *
     * The $onChunk callback is invoked once per text delta as tokens arrive
     * from the provider. The full assembled LlmResponse is returned after
     * the stream completes so the caller can persist the assistant message.
     *
     * @param Agent $agent Must have llm_provider and llm_model set.
     * @param LlmMessage[] $history Ordered conversation history (user + assistant turns).
     * @param string $executionId Correlation ID for logging.
     * @param int|null $userId For log attribution.
     * @param callable(string): void $onChunk Called for each streamed text delta.
     * @throws RuntimeException When the agent has no llm_provider configured.
     */
    public function stream(
        Agent $agent,
        array $history,
        string $executionId,
        ?int $userId,
        callable $onChunk,
    ): LlmResponse {
        $this->assertProviderConfigured($agent);

        $startTime = microtime(true);
        $messages = $this->buildMessages($agent, $history);
        $options = $this->buildOptions($agent);
        $client = $this->clientFactory->make((string)$agent->llm_provider);

        try {
            $response = $client->stream($messages, $options, $onChunk);
        } catch (\Throwable $e) {
            $this->logService->error(
                $agent->id,
                $executionId,
                'LLM streaming call failed',
                $e->getMessage(),
                ['provider' => $agent->llm_provider, 'model' => $agent->llm_model],
                $userId,
            );
            throw $e;
        }

        $durationMs = (int)((microtime(true) - $startTime) * 1000);
        $this->logService->success(
            $agent->id,
            $executionId,
            'LLM streaming call completed',
            $durationMs,
            [
                'provider' => $agent->llm_provider,
                'model' => $response->model,
                'tokens_used' => $response->tokensUsed,
                'finish_reason' => $response->finishReason,
            ],
            $userId,
        );

        return $response;
    }

    /**
     * Calls the LLM for the given agent and waits for the full response
     * (non-streaming). Useful for tests and background jobs.
     *
     * @param Agent $agent Must have llm_provider and llm_model set.
     * @param LlmMessage[] $history Ordered conversation history.
     * @param string $executionId Correlation ID for logging.
     * @param int|null $userId For log attribution.
     * @throws RuntimeException When the agent has no llm_provider configured.
     */
    public function complete(
        Agent $agent,
        array $history,
        string $executionId,
        ?int $userId,
    ): LlmResponse {
        $this->assertProviderConfigured($agent);

        $startTime = microtime(true);
        $messages = $this->buildMessages($agent, $history);
        $options = $this->buildOptions($agent);
        $client = $this->clientFactory->make((string)$agent->llm_provider);

        try {
            $response = $client->complete($messages, $options);
        } catch (\Throwable $e) {
            $this->logService->error(
                $agent->id,
                $executionId,
                'LLM completion call failed',
                $e->getMessage(),
                ['provider' => $agent->llm_provider, 'model' => $agent->llm_model],
                $userId,
            );
            throw $e;
        }

        $durationMs = (int)((microtime(true) - $startTime) * 1000);
        $this->logService->success(
            $agent->id,
            $executionId,
            'LLM completion call completed',
            $durationMs,
            [
                'provider' => $agent->llm_provider,
                'model' => $response->model,
                'tokens_used' => $response->tokensUsed,
            ],
            $userId,
        );

        return $response;
    }

    /**
     * Prepends the agent's system prompt and injects agent_contexts k/v pairs
     * as additional system context before the conversation history.
     *
     * agent_contexts are loaded from the agents.agent_contexts association if
     * it is available on the entity (i.e. the caller contained it).
     *
     * @param LlmMessage[] $history
     * @return LlmMessage[]
     */
    private function buildMessages(Agent $agent, array $history): array
    {
        $systemParts = [];

        if (!empty($agent->instructions)) {
            $systemParts[] = (string)$agent->instructions;
        }

        // Inject agent context key-value pairs as additional system context
        if (!empty($agent->agent_contexts)) {
            $contextLines = [];
            foreach ($agent->agent_contexts as $ctx) {
                $contextLines[] = "{$ctx->key}: {$ctx->value}";
            }
            $systemParts[] = "Agent context:\n" . implode("\n", $contextLines);
        }

        $messages = [];
        if (!empty($systemParts)) {
            $messages[] = new LlmMessage('system', implode("\n\n", $systemParts));
        }

        return array_merge($messages, $history);
    }

    /**
     * Builds the options array passed to the LLM client, carrying the model
     * name and any agent-level config overrides stored in agents.config.
     *
     * @return array<string, mixed>
     */
    private function buildOptions(Agent $agent): array
    {
        $options = [];

        if (!empty($agent->llm_model)) {
            $options['model'] = (string)$agent->llm_model;
        }

        // agents.config stores JSON-encoded overrides (temperature, max_tokens, etc.)
        if (!empty($agent->config)) {
            $config = json_decode((string)$agent->config, true);
            if (is_array($config)) {
                $options = array_merge($options, $config);
            }
        }

        return $options;
    }

    /**
     * @throws RuntimeException When llm_provider is null or empty.
     */
    private function assertProviderConfigured(Agent $agent): void
    {
        if (empty($agent->llm_provider)) {
            throw new RuntimeException(
                "Agent '{$agent->name}' (id={$agent->id}) has no LLM provider configured. " .
                'Set llm_provider and llm_model on the agent before starting a chat session.'
            );
        }
    }
}
