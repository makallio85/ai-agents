<?php
declare(strict_types=1);

namespace App\Integration\Speech;

/**
 * Result of a text-to-speech call.
 *
 * audio is the raw bytes of the synthesised clip; mime tells callers the
 * format so they can hand it to a channel that needs an explicit MIME
 * (WhatsApp Cloud API requires audio/ogg or audio/mp4, etc.).
 */
class TextToSpeechResult
{
    public function __construct(
        public readonly string $audio,
        public readonly string $mime,
    ) {
    }
}
