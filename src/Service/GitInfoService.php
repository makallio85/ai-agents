<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Reads the current git commit hash and subject from the repository.
 *
 * Used by the UI layout to display the deployed code version so operators
 * can quickly verify which commit is running without accessing the server.
 *
 * Falls back to placeholder strings when git is unavailable (e.g. CI
 * containers that run without the .git directory) or when exec() is
 * disabled in PHP configuration.
 */
class GitInfoService
{
    /**
     * Returns ['hash' => '...', 'message' => '...'] for the current HEAD commit.
     *
     * @return array{hash: string, message: string}
     */
    public function head(): array
    {
        if (!$this->execEnabled()) {
            return ['hash' => 'unknown', 'message' => 'git not available'];
        }

        $hash = $this->run('git log -1 --format=%h 2>/dev/null');
        $message = $this->run('git log -1 --format=%s 2>/dev/null');

        return [
            'hash'    => $hash !== '' ? $hash : 'unknown',
            'message' => $message !== '' ? $message : '—',
        ];
    }

    private function run(string $cmd): string
    {
        $output = null;
        @exec($cmd, $output);
        return trim(implode('', (array)$output));
    }

    private function execEnabled(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        $disabled = (string)ini_get('disable_functions');
        return !in_array('exec', array_map('trim', explode(',', $disabled)), true);
    }
}
