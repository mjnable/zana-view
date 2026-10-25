<?php

declare(strict_types=1);

namespace Zana\View\Internal;

use Zana\View\Markup;
use Zana\View\PhpEngine;
use Zana\View\ViewException;
use Zana\View\ViewHelpers;

/**
 * One rendering of a native template; "$this" inside the template file.
 *
 * @internal
 */
final class PhpTemplate
{
    /** @var array<string, string> name => HTML */
    private array $sections;

    /** @var array{string, array<string, mixed>}|null template and extra context */
    private ?array $layout = null;

    /** @var list<string> */
    private array $open = [];

    /**
     * @param array<string, mixed> $context Unescaped
     * @param array<string, string> $sections Sections given by a child template
     */
    public function __construct(
        private readonly PhpEngine $engine,
        private readonly ViewHelpers $helpers,
        private readonly string $template,
        private readonly array $context,
        array $sections = [],
    ) {
        $this->sections = $sections;
    }

    public function render(): string
    {
        $file = $this->engine->file($this->template);
        $level = ob_get_level();
        ob_start();
        try {
            (function (string $__file, array $__variables): void {
                extract($__variables, EXTR_SKIP);
                include $__file;
            })->call($this, $file, self::escapeAll($this->context));
            if ($this->open !== []) {
                throw new ViewException("Template {$this->template}: section \"{$this->open[0]}\" is not closed with stop().");
            }
            $output = (string) ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e instanceof ViewException ? $e : new ViewException("Template {$this->template} failed: {$e->getMessage()}", previous: $e);
        }

        if ($this->layout === null) {
            return $output;
        }

        [$layout, $extra] = $this->layout;
        $sections = $this->sections;
        $sections['content'] ??= $output;

        return (new self($this->engine, $this->helpers, $layout, [...$this->context, ...$extra], $sections))->render();
    }

    /**
     * Escape a value for HTML; Markup is printed as is.
     */
    public function e(mixed $value): string
    {
        if ($value instanceof Markup) {
            return $value->html;
        }
        if ($value === null || is_bool($value)) {
            return $value === true ? '1' : '';
        }
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            throw new ViewException('Cannot print ' . get_debug_type($value) . " in template {$this->template}.");
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * A context value, unescaped.
     */
    public function raw(string $name): mixed
    {
        return $this->context[$name] ?? null;
    }

    /**
     * Render this template inside a layout; its output becomes the layout's
     * "content" section.
     *
     * @param array<string, mixed> $context Added to the layout's context
     */
    public function layout(string $template, array $context = []): void
    {
        $this->layout = [$template, $context];
    }

    public function start(string $section): void
    {
        $this->open[] = $section;
        ob_start();
    }

    public function stop(): void
    {
        $section = array_pop($this->open) ?? throw new ViewException("Template {$this->template}: stop() without start().");
        $html = (string) ob_get_clean();
        // A section a child template defined wins over the layout's own.
        $this->sections[$section] ??= $html;
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    /**
     * Render a partial with this template's context plus $context.
     *
     * @param array<string, mixed> $context
     */
    public function insert(string $template, array $context = []): string
    {
        return (new self($this->engine, $this->helpers, $template, [...$this->context, ...$context]))->render();
    }

    /**
     * View helpers by name; the result is escaped unless the helper is safe.
     *
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): string
    {
        $result = $this->helpers->call($name, $arguments);

        return $this->helpers->isSafe($name) && (is_string($result) || $result instanceof \Stringable) ? (string) $result : $this->e($result);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function escapeAll(array $values): array
    {
        $escaped = [];
        foreach ($values as $key => $value) {
            $escaped[$key] = match (true) {
                is_string($value) => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                is_array($value) => self::escapeAll($value),
                default => $value,
            };
        }

        return $escaped;
    }
}
