<?php

declare(strict_types=1);

namespace Zana\View;

use Closure;

/**
 * Functions and globals available to every template, whatever the engine.
 *
 * A helper's result is escaped unless the helper is declared safe (it
 * returns HTML it built itself, such as csrf_field()). A global may be a
 * Closure, called at each render, e.g. the current locale.
 */
final class ViewHelpers
{
    /** @var array<string, array{Closure, bool}> name => callable, safe */
    private array $helpers = [];

    /** @var array<string, mixed> */
    private array $globals = [];

    public function add(string $name, callable $helper, bool $safe = false): self
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new ViewException("Invalid helper name \"{$name}\".");
        }
        $this->helpers[$name] = [Closure::fromCallable($helper), $safe];

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->helpers[$name]);
    }

    public function isSafe(string $name): bool
    {
        return $this->helpers[$name][1] ?? false;
    }

    /**
     * @param list<mixed> $arguments
     * @throws ViewException For an unknown helper
     */
    public function call(string $name, array $arguments): mixed
    {
        $helper = $this->helpers[$name][0] ?? throw new ViewException("There is no view helper \"{$name}\".");

        return $helper(...$arguments);
    }

    /**
     * @return array<string, Closure> name => callable
     */
    public function callables(): array
    {
        return array_map(static fn (array $helper): Closure => $helper[0], $this->helpers);
    }

    public function addGlobal(string $name, mixed $value): self
    {
        $this->globals[$name] = $value;

        return $this;
    }

    /**
     * The globals, with Closure values called.
     *
     * @return array<string, mixed>
     */
    public function globals(): array
    {
        return array_map(static fn (mixed $value): mixed => $value instanceof Closure ? $value() : $value, $this->globals);
    }
}
