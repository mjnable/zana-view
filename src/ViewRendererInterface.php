<?php

declare(strict_types=1);

namespace Zana\View;

interface ViewRendererInterface
{
    /**
     * Render a template ("pages/home", "@Contact/form") to HTML.
     *
     * @param array<string, mixed> $context
     * @throws ViewException When the template does not exist or fails
     */
    public function render(string $template, array $context = []): string;

    public function exists(string $template): bool;
}
