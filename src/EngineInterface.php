<?php

declare(strict_types=1);

namespace Zana\View;

/**
 * A template engine: the native PhpEngine, or TwigEngine from mj-zana/view-twig.
 * ViewRenderer adds the globals before calling it.
 */
interface EngineInterface
{
    /**
     * @param array<string, mixed> $context
     * @throws ViewException
     */
    public function render(string $template, array $context): string;

    public function exists(string $template): bool;
}
