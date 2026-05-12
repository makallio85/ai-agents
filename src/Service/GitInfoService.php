<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Reads the current git commit hash and subject line for display in the UI.
 *
 * Git info is written to config/git_version.php by the deploy script at
 * deploy time (while the deploying user has access to .git). Reading a
 * pre-generated file avoids runtime exec() calls and .git permission issues
 * when PHP-FPM runs as a different user than the repository owner.
 *
 * Falls back to 'unknown' / '—' when the file is absent (local dev without
 * a deploy, or first run before the file is generated).
 */
class GitInfoService
{
    private const VERSION_FILE = CONFIG . 'git_version.php';

    /**
     * Returns ['hash' => '...', 'message' => '...'] for the deployed commit.
     *
     * @return array{hash: string, message: string}
     */
    public function head(): array
    {
        if (!file_exists(self::VERSION_FILE)) {
            return ['hash' => 'unknown', 'message' => '—'];
        }

        $info = include self::VERSION_FILE;

        if (!is_array($info)) {
            return ['hash' => 'unknown', 'message' => '—'];
        }

        return [
            'hash'    => (string)($info['hash'] ?? 'unknown'),
            'message' => (string)($info['message'] ?? '—'),
        ];
    }
}
