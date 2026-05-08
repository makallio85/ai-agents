<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Service;

use DevOpsOrchestrator\Dto\ParsedIssueDto;

/**
 * Extracts and parses issue blocks from raw conversation text.
 *
 * Issue block format:
 *   === ISSUE START ===
 *   ...content...
 *   === ISSUE END ===
 */
class IssueParserService
{
    private const BLOCK_PATTERN = '/===\s*ISSUE START\s*===\s*(.*?)\s*===\s*ISSUE END\s*===/si';
    private const TITLE_PATTERN = '/^(?:title|TITLE)\s*[:：]\s*(.+)$/mi';
    private const TYPE_PATTERN  = '/^(?:type|TYPE)\s*[:：]\s*(.+)$/mi';
    private const BODY_FIELDS   = ['description', 'body', 'details', 'DESCRIPTION', 'BODY', 'DETAILS'];

    /**
     * Extract all issue blocks from conversation text.
     *
     * @return list<string>
     */
    public function extractRawBlocks(string $text): array
    {
        preg_match_all(self::BLOCK_PATTERN, $text, $matches);

        if (empty($matches[1])) {
            return [];
        }

        return array_values(array_map('trim', $matches[1]));
    }

    /**
     * Parse a single raw block into a structured DTO.
     */
    public function parseBlock(string $rawBlock): ParsedIssueDto
    {
        $title = $this->extractField($rawBlock, self::TITLE_PATTERN);
        $issueType = $this->extractField($rawBlock, self::TYPE_PATTERN);
        $body = $this->extractBody($rawBlock);

        $validationErrors = [];
        if (empty($title)) {
            $validationErrors[] = 'Missing required field: title';
        }
        if (empty($body)) {
            $validationErrors[] = 'Missing required field: body/description';
        }

        if (!empty($validationErrors)) {
            return ParsedIssueDto::invalid($rawBlock, $validationErrors);
        }

        return new ParsedIssueDto(
            rawBlock: $rawBlock,
            title: $title,
            body: $body,
            issueType: $issueType ?: 'general',
            isValid: true
        );
    }

    /**
     * Parse all blocks found in conversation text.
     *
     * @return list<ParsedIssueDto>
     */
    public function parseAll(string $text): array
    {
        $rawBlocks = $this->extractRawBlocks($text);
        return array_map(fn(string $block) => $this->parseBlock($block), $rawBlocks);
    }

    private function extractField(string $text, string $pattern): string
    {
        if (preg_match($pattern, $text, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    private function extractBody(string $rawBlock): string
    {
        foreach (self::BODY_FIELDS as $fieldName) {
            // m flag: ^ matches at start of each line; s flag: . matches newlines; i flag: case-insensitive
            $pattern = '/^' . preg_quote($fieldName, '/') . '\s*[:：]\s*(.+?)(?=\r?\n[A-Za-z][A-Za-z ]+\s*[:：]|\z)/msi';
            if (preg_match($pattern, $rawBlock, $matches)) {
                return trim($matches[1]);
            }
        }

        // Fallback: use all text after removing known labeled fields
        $cleaned = preg_replace('/^[A-Za-z ]+\s*[:：]\s*.+$/m', '', $rawBlock);
        return trim($cleaned ?? '');
    }
}
