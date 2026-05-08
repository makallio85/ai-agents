<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Service;

use DevOpsOrchestrator\Dto\ParsedIssueDto;

class IssueValidatorService
{
    private const MAX_TITLE_LENGTH = 256;
    private const MIN_BODY_LENGTH = 10;

    /**
     * Validate a parsed issue. Returns array of error strings (empty = valid).
     *
     * @return list<string>
     */
    public function validate(ParsedIssueDto $dto): array
    {
        if (!$dto->isValid) {
            return $dto->validationErrors;
        }

        $errors = [];

        if (empty(trim($dto->title))) {
            $errors[] = 'Title is empty';
        } elseif (strlen($dto->title) > self::MAX_TITLE_LENGTH) {
            $errors[] = 'Title exceeds maximum length of ' . self::MAX_TITLE_LENGTH . ' characters';
        }

        if (empty(trim($dto->body))) {
            $errors[] = 'Body is empty';
        } elseif (strlen($dto->body) < self::MIN_BODY_LENGTH) {
            $errors[] = 'Body is too short (minimum ' . self::MIN_BODY_LENGTH . ' characters)';
        }

        return $errors;
    }

    public function isValid(ParsedIssueDto $dto): bool
    {
        return empty($this->validate($dto));
    }
}
