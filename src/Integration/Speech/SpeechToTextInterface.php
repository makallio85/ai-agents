<?php
declare(strict_types=1);

namespace App\Integration\Speech;

/**
 * Provider-agnostic speech-to-text contract.
 *
 * Implementations encapsulate provider quirks (encoding hints, sample rate,
 * model selection) and expose one transcribe() that takes the raw audio
 * bytes plus a MIME hint and returns the text. Tests use an in-memory
 * stub; per CLAUDE.md no test ever hits the network.
 */
interface SpeechToTextInterface
{
    /**
     * @param string $audio Raw audio bytes.
     * @param string $mimeType e.g. "audio/ogg", "audio/mp4", "audio/wav".
     * @param string|null $languageCode BCP-47, e.g. "en-US". Implementations
     *        fall back to the application default when null.
     */
    public function transcribe(string $audio, string $mimeType, ?string $languageCode = null): SpeechToTextResult;
}
