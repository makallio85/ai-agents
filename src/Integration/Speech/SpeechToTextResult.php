<?php
declare(strict_types=1);

namespace App\Integration\Speech;

/**
 * Result of a transcription call.
 *
 * Empty transcript is valid (silent recording) — callers should treat the
 * empty string as "no speech detected" rather than an error. confidence is
 * the provider's average confidence over recognised segments, in [0, 1],
 * or null when the provider does not report one.
 */
class SpeechToTextResult
{
    public function __construct(
        public readonly string $transcript,
        public readonly ?float $confidence = null,
        public readonly ?string $detectedLanguage = null,
    ) {
    }
}
