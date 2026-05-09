<?php
declare(strict_types=1);

namespace App\Service\AgentTools;

use App\Integration\Llm\Tool\ToolDefinition;
use DevOpsOrchestrator\Integration\GitHub\GitHubClientInterface;

/**
 * Provides GitHub tool definitions and their execution logic to AgentLoopService.
 *
 * Each tool maps a well-known snake_case name (registered with the LLM as a
 * function) to a PHP callable that invokes the corresponding GitHubClient
 * method. AgentLoopService calls getDefinitions() to build the `tools` array
 * for the OpenAI request and dispatch() to execute a tool after the LLM
 * requests it.
 *
 * The GitHub token is held by the injected GitHubClientInterface, so this
 * class never handles credentials directly.
 */
class GitHubToolProvider
{
    public function __construct(
        private readonly GitHubClientInterface $github,
    ) {
    }

    /**
     * Returns the full list of ToolDefinitions to offer to the LLM.
     *
     * @return ToolDefinition[]
     */
    public function getDefinitions(): array
    {
        return [
            new ToolDefinition(
                name: 'github_list_repos',
                description: 'List GitHub repositories accessible to this agent.',
                parameters: [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ),
            new ToolDefinition(
                name: 'github_get_file',
                description: 'Read the contents of a file in a GitHub repository.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner (user or org)'],
                        'repo' => ['type' => 'string', 'description' => 'Repository name'],
                        'path' => ['type' => 'string', 'description' => 'File path within the repository'],
                    ],
                    'required' => ['owner', 'repo', 'path'],
                ],
            ),
            new ToolDefinition(
                name: 'github_create_or_update_file',
                description: 'Create or update a file in a GitHub repository (single-file commit). Provide sha when updating an existing file.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                        'repo' => ['type' => 'string', 'description' => 'Repository name'],
                        'path' => ['type' => 'string', 'description' => 'File path within the repository'],
                        'content' => ['type' => 'string', 'description' => 'File content (plain text, will be base64-encoded automatically)'],
                        'message' => ['type' => 'string', 'description' => 'Commit message'],
                        'sha' => ['type' => 'string', 'description' => 'Current file SHA (required when updating an existing file)'],
                        'branch' => ['type' => 'string', 'description' => 'Target branch (defaults to the repo default branch)'],
                    ],
                    'required' => ['owner', 'repo', 'path', 'content', 'message'],
                ],
            ),
            new ToolDefinition(
                name: 'github_create_issue',
                description: 'Create a new issue in a GitHub repository.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                        'repo' => ['type' => 'string', 'description' => 'Repository name'],
                        'title' => ['type' => 'string', 'description' => 'Issue title'],
                        'body' => ['type' => 'string', 'description' => 'Issue description (Markdown)'],
                        'labels' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Label names to apply'],
                    ],
                    'required' => ['owner', 'repo', 'title'],
                ],
            ),
            new ToolDefinition(
                name: 'github_comment_on_issue',
                description: 'Add a comment to an existing GitHub issue or pull request.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                        'repo' => ['type' => 'string', 'description' => 'Repository name'],
                        'issue_number' => ['type' => 'integer', 'description' => 'Issue or PR number'],
                        'body' => ['type' => 'string', 'description' => 'Comment text (Markdown)'],
                    ],
                    'required' => ['owner', 'repo', 'issue_number', 'body'],
                ],
            ),
            new ToolDefinition(
                name: 'github_close_issue',
                description: 'Close an existing GitHub issue.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                        'repo' => ['type' => 'string', 'description' => 'Repository name'],
                        'issue_number' => ['type' => 'integer', 'description' => 'Issue number to close'],
                    ],
                    'required' => ['owner', 'repo', 'issue_number'],
                ],
            ),
            new ToolDefinition(
                name: 'github_create_pull_request',
                description: 'Create a pull request in a GitHub repository.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                        'repo' => ['type' => 'string', 'description' => 'Repository name'],
                        'title' => ['type' => 'string', 'description' => 'PR title'],
                        'body' => ['type' => 'string', 'description' => 'PR description (Markdown)'],
                        'head' => ['type' => 'string', 'description' => 'Source branch name'],
                        'base' => ['type' => 'string', 'description' => 'Target branch name (e.g. main)'],
                    ],
                    'required' => ['owner', 'repo', 'title', 'head', 'base'],
                ],
            ),
        ];
    }

    /**
     * Executes a named tool with the given arguments and returns a plain-text
     * result string to feed back to the LLM.
     *
     * @param string $name Tool name as requested by the LLM.
     * @param array<string, mixed> $args Decoded JSON arguments from the LLM.
     * @throws \InvalidArgumentException When the tool name is unknown.
     */
    public function dispatch(string $name, array $args): string
    {
        return match ($name) {
            'github_list_repos' => $this->listRepos(),
            'github_get_file' => $this->getFile($args),
            'github_create_or_update_file' => $this->createOrUpdateFile($args),
            'github_create_issue' => $this->createIssue($args),
            'github_comment_on_issue' => $this->commentOnIssue($args),
            'github_close_issue' => $this->closeIssue($args),
            'github_create_pull_request' => $this->createPullRequest($args),
            default => throw new \InvalidArgumentException("Unknown tool: {$name}"),
        };
    }

    private function listRepos(): string
    {
        $repos = $this->github->listRepos();
        $names = array_map(fn($r) => $r['full_name'] ?? '', $repos);
        return 'Accessible repositories: ' . implode(', ', array_filter($names));
    }

    /** @param array<string, mixed> $args */
    private function getFile(array $args): string
    {
        $data = $this->github->getFileContents(
            (string)($args['owner'] ?? ''),
            (string)($args['repo'] ?? ''),
            (string)($args['path'] ?? ''),
        );
        $content = base64_decode((string)($data['content'] ?? ''));
        $sha = (string)($data['sha'] ?? '');
        return "sha:{$sha}\n\n{$content}";
    }

    /** @param array<string, mixed> $args */
    private function createOrUpdateFile(array $args): string
    {
        $payload = [
            'message' => (string)($args['message'] ?? 'Update file'),
            'content' => base64_encode((string)($args['content'] ?? '')),
        ];
        if (!empty($args['sha'])) {
            $payload['sha'] = (string)$args['sha'];
        }
        if (!empty($args['branch'])) {
            $payload['branch'] = (string)$args['branch'];
        }
        $result = $this->github->createOrUpdateFile(
            (string)($args['owner'] ?? ''),
            (string)($args['repo'] ?? ''),
            (string)($args['path'] ?? ''),
            $payload,
        );
        $commitSha = (string)($result['commit']['sha'] ?? 'unknown');
        return "File committed successfully. Commit SHA: {$commitSha}";
    }

    /** @param array<string, mixed> $args */
    private function createIssue(array $args): string
    {
        $payload = ['title' => (string)($args['title'] ?? '')];
        if (!empty($args['body'])) {
            $payload['body'] = (string)$args['body'];
        }
        if (!empty($args['labels'])) {
            $payload['labels'] = (array)$args['labels'];
        }
        $result = $this->github->createIssue(
            (string)($args['owner'] ?? ''),
            (string)($args['repo'] ?? ''),
            $payload,
        );
        return "Issue #{$result['number']} created: {$result['html_url']}";
    }

    /** @param array<string, mixed> $args */
    private function commentOnIssue(array $args): string
    {
        $result = $this->github->commentOnIssue(
            (string)($args['owner'] ?? ''),
            (string)($args['repo'] ?? ''),
            (int)($args['issue_number'] ?? 0),
            (string)($args['body'] ?? ''),
        );
        return "Comment added: {$result['html_url']}";
    }

    /** @param array<string, mixed> $args */
    private function closeIssue(array $args): string
    {
        $result = $this->github->closeIssue(
            (string)($args['owner'] ?? ''),
            (string)($args['repo'] ?? ''),
            (int)($args['issue_number'] ?? 0),
        );
        return "Issue #{$result['number']} closed.";
    }

    /** @param array<string, mixed> $args */
    private function createPullRequest(array $args): string
    {
        $result = $this->github->createPullRequest(
            (string)($args['owner'] ?? ''),
            (string)($args['repo'] ?? ''),
            [
                'title' => (string)($args['title'] ?? ''),
                'body' => (string)($args['body'] ?? ''),
                'head' => (string)($args['head'] ?? ''),
                'base' => (string)($args['base'] ?? ''),
            ],
        );
        return "PR #{$result['number']} created: {$result['html_url']}";
    }
}
