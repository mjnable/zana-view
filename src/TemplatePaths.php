<?php

declare(strict_types=1);

namespace Zana\View;

/**
 * Template folders, by namespace. "pages/home" is looked up in the folders
 * without a namespace; "@Contact/form" in the folders of "Contact". Names
 * never contain ".." segments or NUL bytes.
 */
final class TemplatePaths
{
    /** @var array<string, list<string>> namespace ('' for none) => folders, searched in order */
    private array $folders = [];

    public function add(string $directory, ?string $namespace = null): self
    {
        $namespace ??= '';
        if ($namespace !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $namespace) !== 1) {
            throw new ViewException("Invalid template namespace \"{$namespace}\".");
        }
        $this->folders[$namespace][] = rtrim(str_replace('\\', '/', $directory), '/');

        return $this;
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return $this->folders;
    }

    /**
     * The file of a template, with the engine's extension appended unless
     * the name already ends with it.
     *
     * @throws ViewException For an invalid name or a missing template
     */
    public function find(string $name, string $extension): string
    {
        return $this->locate($name, $extension) ?? throw new ViewException("The template \"{$name}\" does not exist.");
    }

    public function exists(string $name, string $extension): bool
    {
        return $this->locate($name, $extension) !== null;
    }

    /**
     * @return array{string, string} the namespace ('' for none) and the path inside it
     * @throws ViewException For an invalid name
     */
    public static function parse(string $name): array
    {
        if ($name === '' || str_contains($name, "\0") || preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $name) === 1) {
            throw new ViewException("Invalid template name \"{$name}\".");
        }

        if (str_starts_with($name, '@')) {
            $slash = strpos($name, '/');
            if ($slash === false) {
                throw new ViewException("Invalid template name \"{$name}\": expected @Namespace/path.");
            }

            return [substr($name, 1, $slash - 1), ltrim(substr($name, $slash + 1), '/')];
        }

        return ['', ltrim($name, '/')];
    }

    private function locate(string $name, string $extension): ?string
    {
        [$namespace, $path] = self::parse($name);
        if (!str_ends_with($path, $extension)) {
            $path .= $extension;
        }

        foreach ($this->folders[$namespace] ?? [] as $folder) {
            if (is_file("{$folder}/{$path}")) {
                return "{$folder}/{$path}";
            }
        }

        return null;
    }
}
