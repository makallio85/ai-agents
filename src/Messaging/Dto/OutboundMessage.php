<?php
declare(strict_types=1);

namespace App\Messaging\Dto;

/**
 * Channel-agnostic outbound payload.
 *
 * Carries the body and any provider-specific metadata (template name + variables
 * for WhatsApp, subject + headers for email) that a transport may consult.
 * Transports that don't recognise specific metadata keys ignore them.
 */
class OutboundMessage
{
    public const CONTENT_TEXT = 'text';
    public const CONTENT_TEMPLATE = 'template';

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $body,
        public readonly string $contentType = self::CONTENT_TEXT,
        public readonly array $metadata = [],
    ) {
    }

    public static function text(string $body): self
    {
        return new self($body);
    }

    /**
     * @param array<string, mixed> $components Provider-specific template components.
     */
    public static function template(string $name, string $language = 'en_US', array $components = []): self
    {
        return new self(
            body: $name,
            contentType: self::CONTENT_TEMPLATE,
            metadata: ['template_name' => $name, 'language' => $language, 'components' => $components],
        );
    }
}
