<?php
/**
 * Plugin configuration.
 *
 * In this file, you configure which plugins are loaded in the different states your app can be.
 * It's loaded via the `parent::bootstrap();` call inside your `Application::bootstrap()` method.
 * For more information see https://book.cakephp.org/5/en/plugins.html#loading-plugins-via-configuration-array
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         5.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

 /*
  * List of plugins to load in the form `PluginName` => `[configuration options]`.
  *
  * Available options:
  * - onlyDebug: Load the plugin only in debug mode. Default false.
  * - onlyCli: Load the plugin only in CLI mode. Default false.
  * - optional: Do not throw an exception if the plugin is not found. Default false.
  */

/*
 * DebugKit auto-disables itself when the HTTP host TLD is not in its safe
 * list (`localhost`, `invalid`, `test`, `example`, `local`, `internal`).
 * On Coolify preview deploys the host is `*.dev.rocksoftware.fi`, which
 * fails that check, and DebugKit emits one `warning` log line per request
 * explaining why it disabled itself. The warning is harmless but it
 * pollutes `logs/error.log` and was flagged in PR #31 review.
 *
 * Skip the plugin entirely on remote hosts (and only there) so the log
 * stays quiet without losing DebugKit in genuine local-dev sessions. The
 * CLI path is always allowed so commands like `bin/cake` keep working.
 * Operators who want DebugKit on a remote preview can opt back in with
 * DEBUG_KIT_FORCE_ENABLE=true.
 */
$debugKitSafeHost = (function (): bool {
    if (PHP_SAPI === 'cli') {
        return true;
    }
    if (filter_var(env('DEBUG_KIT_FORCE_ENABLE', false), FILTER_VALIDATE_BOOLEAN)) {
        return true;
    }
    $host = (string)(env('HTTP_HOST', '') ?: '');
    if ($host === '') {
        return true;
    }
    // Strip an explicit port so `localhost:8765` still matches.
    $host = strtolower(explode(':', $host)[0]);
    $safeTlds = ['localhost', 'invalid', 'test', 'example', 'local', 'internal'];
    foreach ($safeTlds as $tld) {
        if ($host === $tld || str_ends_with($host, '.' . $tld)) {
            return true;
        }
    }
    return false;
})();

$plugins = [
    'Bake' => ['onlyCli' => true, 'optional' => true],
    'Migrations' => ['onlyCli' => true],

    // Authentication & Authorization
    'Authentication' => [],
    'Authorization' => [],

    // Queue processing (registered as Cake/Queue by cakephp/queue package)
    'Cake/Queue' => ['routes' => false],

    // AI Agents
    'DevOpsOrchestrator' => ['routes' => true],
];

if ($debugKitSafeHost) {
    $plugins = ['DebugKit' => ['onlyDebug' => true, 'optional' => true]] + $plugins;
}

return $plugins;
