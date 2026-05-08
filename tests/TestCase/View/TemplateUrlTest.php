<?php
declare(strict_types=1);

namespace App\Test\TestCase\View;

use Cake\TestSuite\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Detects hardcoded absolute URL paths in PHP templates.
 *
 * WHY: The application may be installed in a subdirectory (e.g.
 * http://localhost/ai-agents/). Any href or :href that starts with a literal
 * slash and a known route segment — instead of going through
 * $this->Url->build() — will resolve to the domain root and 404 in
 * subdirectory installs.
 *
 * Vue 3 template expressions are scoped to the component instance, so global
 * JS variables like `window.webroot` are NOT accessible as `webroot` in a
 * :href binding. `:href="webroot + 'agents/view/' + id"` resolves to
 * `_ctx.webroot` which is `undefined`, producing "undefinedagents/view/1".
 *
 * HOW: Scan every .php file under templates/ and assert none contain the
 * antipatterns that caused this bug. The test fails as soon as a new
 * hardcoded path is introduced, preventing regression.
 *
 * ALLOWED PATTERNS:
 *   PHP  → href="<?= $this->Url->build('/agents') ?>"
 *   Vue  → :href="'<?= $this->Url->build('/agents/view/') ?>' + agent.id"
 *   JS   → Api.url('agents/view/' + id)  (for programmatic navigation)
 *
 * FORBIDDEN PATTERNS:
 *   PHP  → href="/agents"
 *   Vue  → :href="'/agents/view/' + id"
 *   Vue  → :href="webroot + 'agents/view/' + id"
 */
class TemplateUrlTest extends TestCase
{
    /**
     * Absolute PHP hrefs that bypass the URL builder and break subdirectory installs.
     * Each entry is [ forbidden_pattern, description ].
     *
     * @var array<array{0: string, 1: string}>
     */
    private array $forbiddenPhpHrefs = [
        ['href="/agents"', 'Hardcoded href="/agents" — use $this->Url->build(\'/agents\')'],
        ['href="/conversations"', 'Hardcoded href="/conversations" — use $this->Url->build(\'/conversations\')'],
        ['href="/dashboard"', 'Hardcoded href="/dashboard" — use $this->Url->build(\'/dashboard\')'],
        ['href="/labels"', 'Hardcoded href="/labels" — use $this->Url->build(\'/labels\')'],
        ['href="/logs"', 'Hardcoded href="/logs" — use $this->Url->build(\'/logs\')'],
        ['href="/github-integrations"', 'Hardcoded href="/github-integrations" — use $this->Url->build(\'/github-integrations\')'],
        ['href="/chat"', 'Hardcoded href="/chat" — use $this->Url->build(\'/chat\')'],
    ];

    /**
     * Vue :href bindings that produce broken URLs in subdirectory installs.
     *
     * Two antipatterns are caught here:
     * 1. Hardcoded leading slash: `:href="'/agents/view/' + id"` — always
     *    resolves from domain root, ignores subdirectory prefix.
     * 2. Global `webroot` variable: `:href="webroot + 'agents/view/' + id"` —
     *    Vue 3 template expressions scope to the component instance; `webroot`
     *    resolves to `_ctx.webroot` which is `undefined`, not `window.webroot`.
     *
     * Correct pattern in PHP templates:
     *   :href="'<?= $this->Url->build('/agents/view/') ?>' + agent.id"
     * PHP bakes the subdirectory-aware prefix at render time; Vue appends only the ID.
     *
     * Each entry is [ forbidden_pattern, description ].
     *
     * @var array<array{0: string, 1: string}>
     */
    private array $forbiddenVueHrefs = [
        [":href=\"'/agents/", "Vue :href with hardcoded '/agents/ — use :href=\"'<?= \$this->Url->build('/agents/view/') ?>' + agent.id\""],
        [":href=\"'/conversations/", "Vue :href with hardcoded '/conversations/ — use :href=\"'<?= \$this->Url->build('/conversations/view/') ?>' + conv.id\""],
        [":href=\"'/dashboard", "Vue :href with hardcoded '/dashboard — use \$this->Url->build('/dashboard')"],
        [":href=\"'/chat/", "Vue :href with hardcoded '/chat/ — use :href=\"'<?= \$this->Url->build('/chat/view/') ?>' + id\""],
        [':href="webroot +', "Vue :href uses global `webroot` variable — inaccessible in Vue 3 component scope; use PHP URL builder to bake the prefix"],
    ];

    /**
     * Assert that no PHP template file contains hardcoded absolute URL paths.
     *
     * This test catches both PHP href attributes and Vue :href bindings that
     * use literal path strings starting with '/' instead of the URL builder
     * or the `webroot` JS variable.
     */
    public function testTemplatesDoNotContainHardcodedAbsoluteUrls(): void
    {
        $templateDir = ROOT . DS . 'templates';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($templateDir));
        $violations = [];

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = (string)file_get_contents($file->getPathname());
            $relativePath = str_replace(ROOT . DS, '', $file->getPathname());

            foreach ($this->forbiddenPhpHrefs as [$pattern, $description]) {
                if (str_contains($content, $pattern)) {
                    $violations[] = "{$relativePath}: {$description}";
                }
            }

            foreach ($this->forbiddenVueHrefs as [$pattern, $description]) {
                if (str_contains($content, $pattern)) {
                    $violations[] = "{$relativePath}: {$description}";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Hardcoded absolute URL paths found in templates.\n"
            . "These break subdirectory installs (e.g. localhost/ai-agents/).\n"
            . "Fix each violation:\n  - " . implode("\n  - ", $violations)
        );
    }
}
