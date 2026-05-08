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
 * $this->Url->build() or the JS `webroot` variable — will resolve to the
 * domain root and 404 in subdirectory installs.
 *
 * HOW: Scan every .php file under templates/ and assert none contain the
 * antipatterns that caused this bug. The test fails as soon as a new
 * hardcoded path is introduced, preventing regression.
 *
 * ALLOWED PATTERNS:
 *   PHP  → href="<?= $this->Url->build('/agents') ?>"
 *   Vue  → :href="webroot + 'agents/view/' + id"
 *
 * FORBIDDEN PATTERNS:
 *   PHP  → href="/agents"
 *   Vue  → :href="'/agents/view/' + id"
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
     * Vue :href bindings with hardcoded leading slash that bypass the `webroot`
     * JS variable and break subdirectory installs.
     * Each entry is [ forbidden_pattern, description ].
     *
     * @var array<array{0: string, 1: string}>
     */
    private array $forbiddenVueHrefs = [
        [":href=\"'/agents/", "Vue :href with hardcoded '/agents/ — use :href=\"webroot + 'agents/...\""],
        [":href=\"'/conversations/", "Vue :href with hardcoded '/conversations/ — use :href=\"webroot + 'conversations/...\""],
        [":href=\"'/dashboard", "Vue :href with hardcoded '/dashboard — use :href=\"webroot + 'dashboard'\""],
        [":href=\"'/chat/", "Vue :href with hardcoded '/chat/ — use :href=\"webroot + 'chat/...\""],
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
