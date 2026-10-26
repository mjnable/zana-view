<?php

declare(strict_types=1);

namespace Zana\View;

/**
 * Versioned asset URLs (FE-19), so assets can be cached for a year
 * (Cache-Control: immutable) and still change when they are rebuilt:
 *
 * - "mtime": /assets/app.css?v=<modification time>
 * - "hash": /assets/app.css?v=<content hash>
 * - "manifest": the file a build tool wrote in its manifest, e.g. Vite's
 *   manifest.json: asset('src/app.js') gives /build/assets/app-4f3c2a.js
 *
 * URLs start with the application's base path.
 */
final class Assets
{
    /** @var array<string, string> */
    private array $versions = [];

    /** @var array<string, mixed>|null */
    private ?array $manifest = null;

    /**
     * @param string $directory The public directory, e.g. <base>/public
     * @param 'mtime'|'hash'|'manifest' $strategy
     * @param string|null $manifestFile Relative to $directory, e.g. build/manifest.json
     */
    public function __construct(
        private readonly string $directory,
        private readonly string $basePath = '',
        private readonly string $strategy = 'mtime',
        private readonly ?string $manifestFile = null,
    ) {
    }

    public function url(string $path): string
    {
        $path = ltrim($path, '/');
        if (str_contains($path, '..')) {
            throw new ViewException("Invalid asset path \"{$path}\".");
        }

        if ($this->strategy === 'manifest') {
            return $this->basePath . '/' . $this->fromManifest($path);
        }

        $this->versions[$path] ??= $this->version("{$this->directory}/{$path}");

        return "{$this->basePath}/{$path}" . ($this->versions[$path] === '' ? '' : '?v=' . $this->versions[$path]);
    }

    private function version(string $file): string
    {
        if (!is_file($file)) {
            return '';
        }

        return $this->strategy === 'hash'
            ? substr((string) hash_file('xxh128', $file), 0, 12)
            : base_convert((string) filemtime($file), 10, 36);
    }

    private function fromManifest(string $entry): string
    {
        if ($this->manifestFile === null) {
            throw new ViewException('The "manifest" asset strategy needs view.assets.manifest.');
        }
        if ($this->manifest === null) {
            $file = "{$this->directory}/{$this->manifestFile}";
            $decoded = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            $this->manifest = is_array($decoded) ? $decoded : throw new ViewException("Cannot read the asset manifest {$file}.");
        }

        $item = $this->manifest[$entry] ?? null;
        $built = is_array($item) ? ($item['file'] ?? null) : $item;
        if (!is_string($built)) {
            throw new ViewException("The asset manifest has no entry \"{$entry}\".");
        }
        $folder = trim(dirname($this->manifestFile), './');
        $folder = $folder === '.vite' || str_ends_with($folder, '/.vite') ? trim(dirname($folder), './') : $folder;

        return ($folder === '' ? '' : "{$folder}/") . ltrim($built, '/');
    }
}
