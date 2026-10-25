<?php

declare(strict_types=1);

namespace Zana\View;

use Zana\Http\RequestContext;
use Zana\Http\SecurityHeadersMiddleware;

/**
 * Renders templates with the engine, adding the globals: view.globals,
 * ViewHelpers globals, and "csp_nonce" (the request's Content-Security-Policy
 * nonce, when SecurityHeadersMiddleware generated one). Context values
 * override globals. Registered scoped, as it reads the current request.
 */
final readonly class ViewRenderer implements ViewRendererInterface
{
    /**
     * @param array<string, mixed> $globals
     */
    public function __construct(
        private EngineInterface $engine,
        private ViewHelpers $helpers,
        private array $globals = [],
        private ?RequestContext $request = null,
    ) {
    }

    public function render(string $template, array $context = []): string
    {
        $nonce = $this->request?->has() === true ? $this->request->request()->getAttribute(SecurityHeadersMiddleware::NONCE_ATTRIBUTE) : null;

        return $this->engine->render($template, [
            ...$this->globals,
            ...$this->helpers->globals(),
            'csp_nonce' => is_string($nonce) ? $nonce : null,
            ...$context,
        ]);
    }

    public function exists(string $template): bool
    {
        return $this->engine->exists($template);
    }
}
