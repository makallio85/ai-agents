<?php
declare(strict_types=1);

namespace App\Integration\Speech;

/**
 * Provider-agnostic text-to-speech contract.
 *
 * Implementations choose the optimal voice / format defaults for each
 * channel — WhatsApp's Cloud API accepts audio/ogg (Opus) and audio/mp4
 * for outbound voice notes; Slack files.upload takes whatever you give it.
 * Callers pass language as a hint; provider picks an appropriate voice.
 */
interface TextToSpeechInterface
{
    /**
     * @param string $text The transcript to synthesise.
     * @param string|null $languageCode BCP-47 e.g. "en-US"; provider default if null.
     * @param string|null $audioFormat Optional MIME hint for the channel.
     */
    public function synthesise(string $text, ?string $languageCode = null, ?string $audioFormat = null): TextToSpeechResult;
}
