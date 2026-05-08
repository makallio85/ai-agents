<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Dto;

class ParsedIssueDto
{
    /**
     * @param list<string> $labels
     * @param list<string> $validationErrors
     */
    public function __construct(
        public readonly string $rawBlock,
        public readonly string $title,
        public readonly string $body,
        public readonly string $issueType,
        public readonly array $labels = [],
        public readonly array $validationErrors = [],
        public readonly bool $isValid = true
    ) {
    }

    public static function invalid(string $rawBlock, array $validationErrors): self
    {
        return new self(
            rawBlock: $rawBlock,
            title: '',
            body: '',
            issueType: '',
            labels: [],
            validationErrors: $validationErrors,
            isValid: false
        );
    }
}
