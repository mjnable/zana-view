<?php

declare(strict_types=1);

namespace Zana\View;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zana\Http\ErrorRendererInterface;
use Zana\Http\HtmlErrorPage;
use Zana\Http\ResponseFactory;

/**
 * Error pages from templates: errors/<status> (e.g. errors/404), otherwise
 * errors/error, with "status", "title" (the reason phrase) and "detail" in the
 * context. Without either template, the built-in page.
 */
final readonly class ViewErrorRenderer implements ErrorRendererInterface
{
    public function __construct(
        private ViewRendererInterface $views,
        private ResponseFactory $responses,
    ) {
    }

    public function render(\Throwable $exception, ServerRequestInterface $request, int $status, ?string $detail): ResponseInterface
    {
        $title = $this->responses->create($status)->getReasonPhrase();

        foreach (["errors/{$status}", 'errors/error'] as $template) {
            if ($this->views->exists($template)) {
                $html = $this->views->render($template, ['status' => $status, 'title' => $title, 'detail' => $detail]);

                return $this->responses->html($html, $status);
            }
        }

        return $this->responses->html(HtmlErrorPage::render($status, $title, $detail), $status);
    }
}
