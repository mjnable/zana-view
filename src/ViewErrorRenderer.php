<?php

declare(strict_types=1);

namespace Zana\View;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zana\Http\ErrorRendererInterface;
use Closure;
use Zana\Http\HtmlErrorPage;
use Zana\Http\ResponseFactory;

/**
 * Error pages from templates: errors/<status> (e.g. errors/404), otherwise
 * errors/error, with "status", "title" (the reason phrase) and "detail" in the
 * context. Without either template, the built-in page.
 *
 * Error pages render outside the application's middleware, so $inLocale (set
 * by the provider when mj-zana/translation is installed) renders them in the
 * request's locale.
 */
final readonly class ViewErrorRenderer implements ErrorRendererInterface
{
    /**
     * @param (Closure(ServerRequestInterface, Closure(): string): string)|null $inLocale
     */
    public function __construct(
        private ViewRendererInterface $views,
        private ResponseFactory $responses,
        private ?Closure $inLocale = null,
    ) {
    }

    public function render(\Throwable $exception, ServerRequestInterface $request, int $status, ?string $detail): ResponseInterface
    {
        $title = $this->responses->create($status)->getReasonPhrase();

        foreach (["errors/{$status}", 'errors/error'] as $template) {
            if ($this->views->exists($template)) {
                $render = fn (): string => $this->views->render($template, ['status' => $status, 'title' => $title, 'detail' => $detail]);
                $html = $this->inLocale === null ? $render() : ($this->inLocale)($request, $render);

                return $this->responses->html($html, $status);
            }
        }

        return $this->responses->html(HtmlErrorPage::render($status, $title, $detail), $status);
    }
}
