<?php

declare(strict_types=1);

namespace Zana\View;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zana\Http\ResponseFactory;
use Zana\Routing\ResultConverterInterface;

/**
 * Turns View results of route handlers into HTML responses.
 */
final readonly class ViewResultConverter implements ResultConverterInterface
{
    public function __construct(
        private ViewRendererInterface $views,
        private ResponseFactory $responses,
    ) {
    }

    public function supports(mixed $result): bool
    {
        return $result instanceof View;
    }

    public function convert(mixed $result, ServerRequestInterface $request): ResponseInterface
    {
        assert($result instanceof View);

        return $this->responses->html($this->views->render($result->template, $result->context), $result->status, $result->headers);
    }
}
