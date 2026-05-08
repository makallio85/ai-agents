<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Dto;

class GitHubIssueDto
{
    /**
     * @param list<string> $labels
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $labels = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'labels' => $this->labels,
        ];
    }
}
