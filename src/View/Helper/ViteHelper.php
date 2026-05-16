<?php
declare(strict_types=1);

namespace App\View\Helper;

use Cake\Core\Configure;
use Cake\View\Helper;
use Cake\View\StringTemplateTrait;

/**
 * Loads Vite-built assets via the manifest at /webroot/build/manifest.json.
 *
 * Usage in templates:
 *   echo $this->Vite->css('resources/js/app.js');
 *   echo $this->Vite->js('resources/js/app.js');
 *
 * In DEBUG mode with `Vite.devServer` set in app config, falls back to the
 * Vite dev server (HMR). In production (no debug or no dev server), reads
 * the manifest to get the hashed filename.
 */
class ViteHelper extends Helper
{
    use StringTemplateTrait;

    protected array $_defaultConfig = [
        'manifestPath' => WWW_ROOT . 'build' . DS . 'manifest.json',
        'buildBase'    => '/build/',
        'devServer'    => null,  // e.g. 'http://localhost:5173' when running `npm run dev`
    ];

    private ?array $manifest = null;

    private function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }
        $path = $this->getConfig('manifestPath');
        if (!is_file($path)) {
            return $this->manifest = [];
        }
        $raw = @file_get_contents($path);
        $this->manifest = $raw ? (json_decode($raw, true) ?? []) : [];
        return $this->manifest;
    }

    private function devServer(): ?string
    {
        $configured = $this->getConfig('devServer') ?? Configure::read('Vite.devServer');
        return Configure::read('debug') && $configured ? rtrim($configured, '/') : null;
    }

    public function js(string $entry): string
    {
        if ($dev = $this->devServer()) {
            return $this->scriptTag("{$dev}/@vite/client") . $this->scriptTag("{$dev}/{$entry}");
        }
        $m = $this->manifest()[$entry] ?? null;
        if (!$m || empty($m['file'])) {
            return '';
        }
        return $this->scriptTag($this->getConfig('buildBase') . $m['file']);
    }

    public function css(string $entry): string
    {
        if ($this->devServer()) {
            return ''; // dev server injects CSS via JS
        }
        $m = $this->manifest()[$entry] ?? null;
        if (!$m || empty($m['css'])) {
            return '';
        }
        $out = '';
        foreach ($m['css'] as $href) {
            $out .= '<link rel="stylesheet" href="' . h($this->getConfig('buildBase') . $href) . '">';
        }
        return $out;
    }

    private function scriptTag(string $src): string
    {
        return '<script type="module" src="' . h($src) . '"></script>';
    }
}
