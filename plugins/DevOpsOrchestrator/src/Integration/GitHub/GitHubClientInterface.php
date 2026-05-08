<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Integration\GitHub;

interface GitHubClientInterface
{
    /**
     * Create an issue in the repository.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed> Created issue data (number, html_url, etc.)
     * @throws \DevOpsOrchestrator\Integration\GitHub\GitHubException
     */
    public function createIssue(string $owner, string $repo, array $payload): array;

    /**
     * Ensure labels exist on the repository. Creates missing ones.
     *
     * @param list<string> $labelSlugs
     */
    public function ensureLabels(string $owner, string $repo, array $labelSlugs): void;

    /**
     * Get all labels for a repository.
     *
     * @return list<array<string, mixed>>
     */
    public function getLabels(string $owner, string $repo): array;
}
