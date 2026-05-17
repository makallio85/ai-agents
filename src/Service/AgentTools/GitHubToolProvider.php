<?php
declare(strict_types=1);

namespace App\Service\AgentTools;

use App\Integration\Llm\Tool\ToolDefinition;
use App\Service\AgentIntegrationPermissionService;
use App\Service\AgentPermissionSet;
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
 *
 * Write tools (create_or_update_file, create_pull_request) are intentionally
 * absent — the bot is read-only for issue management and code navigation.
 */
class GitHubToolProvider
{
    private readonly AgentIntegrationPermissionService $permissionService;

    /**
     * @param AgentPermissionSet|null $permissionSet When supplied, getDefinitions()
     *   filters out tools whose required action has not been granted and dispatch()
     *   throws PermissionDeniedException for ungranted actions (issue #9). When null
     *   (legacy / non-agent callers), every tool is exposed and no enforcement occurs.
     * @param AgentIntegrationPermissionService|null $permissionService Resolves
     *   tool→action; defaults to a fresh instance so callers don't have to wire it.
     */
    public function __construct(
        private readonly GitHubClientInterface $github,
        private readonly ?AgentPermissionSet $permissionSet = null,
        ?AgentIntegrationPermissionService $permissionService = null,
    ) {
        $this->permissionService = $permissionService ?? new AgentIntegrationPermissionService();
    }

    /**
     * Returns the list of ToolDefinitions the LLM is allowed to see.
     *
     * When a permission set has been injected, definitions for tools whose
     * required action has not been granted to the agent are dropped — the
     * LLM cannot even request them. Tools not gated by an action are always
     * exposed. When no permission set is supplied, every tool is returned.
     *
     * @return ToolDefinition[]
     */
    public function getDefinitions(): array
    {
        $all = $this->buildAllDefinitions();
        if ($this->permissionSet === null) {
            return $all;
        }

        return array_values(array_filter(
            $all,
            fn(ToolDefinition $tool) => $this->isToolAllowed($tool->name),
        ));
    }

    /**
     * Builds every ToolDefinition supported by the provider.
     *
     * @return ToolDefinition[]
     */
    private function buildAllDefinitions(): array
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
                name: 'github_list_issues',
                description: 'List issues in a GitHub repository. Returns issue numbers, titles, states, labels, and URLs.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                        'repo' => ['type' => 'string', 'description' => 'Repository name'],
                        'state' => ['type' => 'string', 'enum' => ['open', 'closed', 'all'], 'description' => 'Filter by state (default: open)'],
                        'labels' => ['type' => 'string', 'description' => 'Comma-separated label names to filter by (optional)'],
                    ],
                    'required' => ['owner', 'repo'],
                ],
            ),
            new ToolDefinition(
                name: 'github_get_issue',
                description: 'Get the full details of a single GitHub issue including title, body, state, labels, assignees, and comments count.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                        'repo' => ['type' => 'string', 'description' => 'Repository name'],
                        'issue_number' => ['type' => 'integer', 'description' => 'Issue number'],
                    ],
                    'required' => ['owner', 'repo', 'issue_number'],
                ],
            ),
            new ToolDefinition(
                name: 'github_list_commits',
                description: 'List recent commits on a branch. Returns SHA, message, author, and date.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                        'repo' => ['type' => 'string', 'description' => 'Repository name'],
                        'branch' => ['type' => 'string', 'description' => 'Branch name (defaults to the default branch)'],
                        'per_page' => ['type' => 'integer', 'description' => 'Number of commits to return (default: 20, max: 100)'],
                    ],
                    'required' => ['owner', 'repo'],
                ],
            ),
            new ToolDefinition(
                name: 'github_get_commit',
                description: 'Get full details of a single commit including the list of changed files and their diffs.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                        'repo' => ['type' => 'string', 'description' => 'Repository name'],
                        'sha' => ['type' => 'string', 'description' => 'Commit SHA (full or abbreviated)'],
                    ],
                    'required' => ['owner', 'repo', 'sha'],
                ],
            ),
            new ToolDefinition(
                name: 'github_list_directory',
                description: 'List the contents of a directory in a GitHub repository. Use path="" for root, or a relative path like "src/Controller".',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                        'repo' => ['type' => 'string', 'description' => 'Repository name'],
                        'path' => ['type' => 'string', 'description' => 'Directory path (empty string = repo root)'],
                        'branch' => ['type' => 'string', 'description' => 'Branch name (defaults to the default branch)'],
                    ],
                    'required' => ['owner', 'repo'],
                ],
            ),
            new ToolDefinition(
                name: 'github_list_pull_requests',
                description: 'List pull requests in a GitHub repository. Use this to find open PRs before asking the user for a PR number.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                        'repo' => ['type' => 'string', 'description' => 'Repository name'],
                        'state' => ['type' => 'string', 'enum' => ['open', 'closed', 'all'], 'description' => 'Filter by state (default: open)'],
                    ],
                    'required' => ['owner', 'repo'],
                ],
            ),
            new ToolDefinition(
                name: 'github_get_pull_request',
                description: 'Get details of a specific pull request including title, description, author, base/head branches, and merge status.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                        'repo' => ['type' => 'string', 'description' => 'Repository name'],
                        'pull_number' => ['type' => 'integer', 'description' => 'Pull request number'],
                    ],
                    'required' => ['owner', 'repo', 'pull_number'],
                ],
            ),
            new ToolDefinition(
                name: 'github_get_pull_request_files',
                description: 'Get the list of files changed in a pull request, including the patch diff for each file. Use this to review code changes.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                        'repo' => ['type' => 'string', 'description' => 'Repository name'],
                        'pull_number' => ['type' => 'integer', 'description' => 'Pull request number'],
                    ],
                    'required' => ['owner', 'repo', 'pull_number'],
                ],
            ),
            new ToolDefinition(
                name: 'github_get_pull_request_commits',
                description: 'Get the list of commits in a pull request with their messages and authors.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                        'repo' => ['type' => 'string', 'description' => 'Repository name'],
                        'pull_number' => ['type' => 'integer', 'description' => 'Pull request number'],
                    ],
                    'required' => ['owner', 'repo', 'pull_number'],
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
     * @throws PermissionDeniedException When the agent has no grant for the
     *   action this tool requires (only when a permission set is injected).
     */
    public function dispatch(string $name, array $args): string
    {
        $this->assertToolAllowed($name);

        return match ($name) {
            'github_list_repos' => $this->listRepos(),
            'github_get_file' => $this->getFile($args),
            'github_create_issue' => $this->createIssue($args),
            'github_comment_on_issue' => $this->commentOnIssue($args),
            'github_close_issue' => $this->closeIssue($args),
            'github_list_issues' => $this->listIssues($args),
            'github_get_issue' => $this->getIssue($args),
            'github_list_commits' => $this->listCommits($args),
            'github_get_commit' => $this->getCommit($args),
            'github_list_directory' => $this->listDirectory($args),
            'github_list_pull_requests' => $this->listPullRequests($args),
            'github_get_pull_request' => $this->getPullRequest($args),
            'github_get_pull_request_files' => $this->getPullRequestFiles($args),
            'github_get_pull_request_commits' => $this->getPullRequestCommits($args),
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
    private function listIssues(array $args): string
    {
        $issues = $this->github->listIssues(
            (string)($args['owner'] ?? ''),
            (string)($args['repo'] ?? ''),
            (string)($args['state'] ?? 'open'),
            isset($args['labels']) && $args['labels'] !== '' ? (string)$args['labels'] : null,
        );
        if (empty($issues)) {
            return 'No issues found.';
        }
        $lines = array_map(fn($i) => sprintf(
            '#%d [%s] "%s" — %s',
            $i['number'] ?? 0,
            $i['state'] ?? '',
            $i['title'] ?? '',
            $i['html_url'] ?? '',
        ), $issues);
        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $args */
    private function getIssue(array $args): string
    {
        $issue = $this->github->getIssue(
            (string)($args['owner'] ?? ''),
            (string)($args['repo'] ?? ''),
            (int)($args['issue_number'] ?? 0),
        );
        $labels = implode(', ', array_map(fn($l) => (string)($l['name'] ?? ''), (array)($issue['labels'] ?? [])));
        $assignees = implode(', ', array_map(fn($a) => (string)($a['login'] ?? ''), (array)($issue['assignees'] ?? [])));
        return sprintf(
            "Issue #%d: %s\nState: %s\nLabels: %s\nAssignees: %s\nComments: %d\nURL: %s\n\n%s",
            $issue['number'] ?? 0,
            $issue['title'] ?? '',
            $issue['state'] ?? '',
            $labels ?: '(none)',
            $assignees ?: '(none)',
            $issue['comments'] ?? 0,
            $issue['html_url'] ?? '',
            $issue['body'] ?? '(no description)',
        );
    }

    /** @param array<string, mixed> $args */
    private function listCommits(array $args): string
    {
        $commits = $this->github->listCommits(
            (string)($args['owner'] ?? ''),
            (string)($args['repo'] ?? ''),
            (string)($args['branch'] ?? ''),
            min(100, max(1, (int)($args['per_page'] ?? 20))),
        );
        if (empty($commits)) {
            return 'No commits found.';
        }
        $lines = array_map(fn($c) => sprintf(
            '%s %s (%s, %s)',
            substr((string)($c['sha'] ?? ''), 0, 7),
            $c['commit']['message'] ?? '',
            $c['commit']['author']['name'] ?? '',
            $c['commit']['author']['date'] ?? '',
        ), $commits);
        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $args */
    private function getCommit(array $args): string
    {
        $commit = $this->github->getCommit(
            (string)($args['owner'] ?? ''),
            (string)($args['repo'] ?? ''),
            (string)($args['sha'] ?? ''),
        );
        $files = (array)($commit['files'] ?? []);
        $fileLines = array_map(fn($f) => sprintf(
            "--- %s [%s] +%d -%d ---\n%s",
            $f['filename'] ?? '',
            $f['status'] ?? '',
            $f['additions'] ?? 0,
            $f['deletions'] ?? 0,
            $f['patch'] ?? '(binary or no diff)',
        ), $files);
        return sprintf(
            "%s %s\nAuthor: %s <%s>\nDate: %s\n\n%s\n\nFiles changed:\n%s",
            substr((string)($commit['sha'] ?? ''), 0, 7),
            $commit['commit']['message'] ?? '',
            $commit['commit']['author']['name'] ?? '',
            $commit['commit']['author']['email'] ?? '',
            $commit['commit']['author']['date'] ?? '',
            $commit['html_url'] ?? '',
            implode("\n\n", $fileLines) ?: '(none)',
        );
    }

    /** @param array<string, mixed> $args */
    private function listDirectory(array $args): string
    {
        $entries = $this->github->listDirectory(
            (string)($args['owner'] ?? ''),
            (string)($args['repo'] ?? ''),
            (string)($args['path'] ?? ''),
            (string)($args['branch'] ?? ''),
        );
        if (empty($entries)) {
            return 'Directory is empty.';
        }
        $lines = array_map(fn($e) => sprintf(
            '[%s] %s',
            $e['type'] ?? 'file',
            $e['path'] ?? $e['name'] ?? '',
        ), $entries);
        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $args */
    private function listPullRequests(array $args): string
    {
        $prs = $this->github->listPullRequests(
            (string)($args['owner'] ?? ''),
            (string)($args['repo'] ?? ''),
            (string)($args['state'] ?? 'open'),
        );
        if (empty($prs)) {
            return 'No pull requests found.';
        }
        $lines = array_map(fn($pr) => sprintf(
            'PR #%d [%s] "%s" — %s → %s — %s',
            $pr['number'] ?? 0,
            $pr['state'] ?? '',
            $pr['title'] ?? '',
            $pr['head']['ref'] ?? '',
            $pr['base']['ref'] ?? '',
            $pr['html_url'] ?? '',
        ), $prs);
        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $args */
    private function getPullRequest(array $args): string
    {
        $pr = $this->github->getPullRequest(
            (string)($args['owner'] ?? ''),
            (string)($args['repo'] ?? ''),
            (int)($args['pull_number'] ?? 0),
        );
        return sprintf(
            "PR #%d: %s\nAuthor: %s\nState: %s\nBase: %s ← Head: %s\nMergeable: %s\nURL: %s\n\n%s",
            $pr['number'] ?? 0,
            $pr['title'] ?? '',
            $pr['user']['login'] ?? '',
            $pr['state'] ?? '',
            $pr['base']['ref'] ?? '',
            $pr['head']['ref'] ?? '',
            ($pr['mergeable'] ?? null) === true ? 'yes' : (($pr['mergeable'] ?? null) === false ? 'no (conflicts)' : 'unknown'),
            $pr['html_url'] ?? '',
            $pr['body'] ?? '(no description)',
        );
    }

    /** @param array<string, mixed> $args */
    private function getPullRequestFiles(array $args): string
    {
        $files = $this->github->getPullRequestFiles(
            (string)($args['owner'] ?? ''),
            (string)($args['repo'] ?? ''),
            (int)($args['pull_number'] ?? 0),
        );
        if (empty($files)) {
            return 'No files changed.';
        }
        $lines = [];
        foreach ($files as $file) {
            $lines[] = sprintf(
                "--- %s [%s] +%d -%d ---\n%s",
                $file['filename'] ?? '',
                $file['status'] ?? '',
                $file['additions'] ?? 0,
                $file['deletions'] ?? 0,
                $file['patch'] ?? '(binary or no diff)',
            );
        }
        return implode("\n\n", $lines);
    }

    /** @param array<string, mixed> $args */
    private function getPullRequestCommits(array $args): string
    {
        $commits = $this->github->getPullRequestCommits(
            (string)($args['owner'] ?? ''),
            (string)($args['repo'] ?? ''),
            (int)($args['pull_number'] ?? 0),
        );
        if (empty($commits)) {
            return 'No commits found.';
        }
        $lines = array_map(fn($c) => sprintf(
            '%s %s (%s)',
            substr((string)($c['sha'] ?? ''), 0, 7),
            $c['commit']['message'] ?? '',
            $c['commit']['author']['name'] ?? '',
        ), $commits);
        return implode("\n", $lines);
    }

    /**
     * Returns true when the agent is allowed to call the named tool. Tools
     * that are not gated by an action are always allowed; if no permission
     * set was injected the provider is permissive (legacy callers).
     */
    private function isToolAllowed(string $toolName): bool
    {
        if ($this->permissionSet === null) {
            return true;
        }
        $required = $this->permissionService->getActionForTool($toolName);
        if ($required === null) {
            return true;
        }

        return $this->permissionSet->has($required);
    }

    /**
     * Throws PermissionDeniedException when the agent has no grant for the
     * action the named tool requires. No-op when no permission set is
     * injected.
     */
    private function assertToolAllowed(string $toolName): void
    {
        if ($this->permissionSet === null) {
            return;
        }
        $required = $this->permissionService->getActionForTool($toolName);
        if ($required === null) {
            return;
        }
        if (!$this->permissionSet->has($required)) {
            throw new PermissionDeniedException($toolName, $required);
        }
    }
}
