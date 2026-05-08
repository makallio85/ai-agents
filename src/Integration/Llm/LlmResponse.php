<?php
declare(strict_types=1);

namespace App\Integration\Llm;

/**
 * Carries the completed result from an LLM call.
 *
 * Returned by both complete() and stream() on LlmClientInterface after the
 * full response has been assembled. tokensUsed is provider-reported and may
 * be null when the provider does not include usage in its streaming response.
 * model is the actual model identifier echoed back by the provider, which may
 * differ from the requested model when an alias like "gpt-4o" resolves to a
 * versioned model name.
 */
class LlmResponse
{
    public function __construct(
        public readonly string $content,
        public readonly ?int $tokensUsed,
        public readonly string $model,
        public readonly string $finishReason,
    ) {
    }
}
