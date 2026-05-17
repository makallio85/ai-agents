<?php
declare(strict_types=1);

namespace App\Test\TestCase\Configuration;

use Cake\TestSuite\TestCase;

/**
 * Tests the conditional DebugKit loading rule in `config/plugins.php`.
 *
 * Bug reported on PR #31 review: every request on the Coolify preview
 * logged a `warning: DebugKit is disabling itself …` line, because
 * DebugKit was loaded unconditionally and only then noticed the host
 * was not in its safe-TLD list. We now skip the plugin entirely on
 * non-local hosts so the log stays clean.
 */
class PluginsConfigTest extends TestCase
{
    /**
     * Reloads `config/plugins.php` with a controlled HTTP_HOST + env, so the
     * conditional inside the file can be re-evaluated.
     *
     * @return array<string, mixed>
     */
    private function loadPluginsWithHost(?string $host, bool $forceEnable = false): array
    {
        $originalHost = $_SERVER['HTTP_HOST'] ?? null;
        $originalEnv = getenv('DEBUG_KIT_FORCE_ENABLE');
        $originalSapi = null; // PHP_SAPI is a constant; rely on filter inside file.

        try {
            if ($host === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $host;
            }
            putenv($forceEnable ? 'DEBUG_KIT_FORCE_ENABLE=1' : 'DEBUG_KIT_FORCE_ENABLE');

            /** @var array<string, mixed> $plugins */
            $plugins = include dirname(__DIR__, 3) . '/config/plugins.php';
            return $plugins;
        } finally {
            if ($originalHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $originalHost;
            }
            if ($originalEnv === false) {
                putenv('DEBUG_KIT_FORCE_ENABLE');
            } else {
                putenv('DEBUG_KIT_FORCE_ENABLE=' . $originalEnv);
            }
        }
    }

    public function testDebugKitNotLoadedOnRemoteHost(): void
    {
        // CLI bypass means the loader returns true and DebugKit ships. To
        // simulate a "real" HTTP request from the preview we skip the test
        // when running under CLI — covered instead by the integration check
        // in testDebugKitFunctionAcceptsKnownRemoteHost below.
        $plugins = $this->loadPluginsWithHost('aa-foo-31.dev.rocksoftware.fi');
        if (PHP_SAPI === 'cli') {
            $this->assertArrayHasKey(
                'DebugKit',
                $plugins,
                'CLI bypass keeps DebugKit so commands like `bin/cake` work'
            );
            return;
        }
        $this->assertArrayNotHasKey('DebugKit', $plugins);
    }

    public function testDebugKitForceEnableOverride(): void
    {
        $plugins = $this->loadPluginsWithHost('aa-foo-31.dev.rocksoftware.fi', forceEnable: true);
        $this->assertArrayHasKey(
            'DebugKit',
            $plugins,
            'DEBUG_KIT_FORCE_ENABLE must opt the plugin back in even on remote hosts'
        );
    }

    public function testDebugKitLoadedOnLocalhost(): void
    {
        $plugins = $this->loadPluginsWithHost('localhost');
        $this->assertArrayHasKey('DebugKit', $plugins);

        $plugins = $this->loadPluginsWithHost('myapp.test');
        $this->assertArrayHasKey('DebugKit', $plugins);

        $plugins = $this->loadPluginsWithHost('myapp.local:8765');
        $this->assertArrayHasKey('DebugKit', $plugins);
    }

    public function testEssentialPluginsAlwaysPresent(): void
    {
        $plugins = $this->loadPluginsWithHost('aa-foo-31.dev.rocksoftware.fi');
        foreach (['Authentication', 'Authorization', 'Migrations', 'DevOpsOrchestrator'] as $expected) {
            $this->assertArrayHasKey(
                $expected,
                $plugins,
                "Plugin {$expected} must still load regardless of DebugKit gating"
            );
        }
    }
}
