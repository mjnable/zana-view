<?php

declare(strict_types=1);

namespace Zana\View;

/**
 * A page to render, returned by a route handler:
 *
 *     return View::make('@Contact/form', ['errors' => $errors])->withStatus(422);
 */
final readonly class View
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $template,
        public array $context = [],
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function make(string $template, array $context = []): self
    {
        return new self($template, $context);
    }

    public function withStatus(int $status): self
    {
        return new self($this->template, $this->context, $status, $this->headers);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->template, $this->context, $this->status, [...$this->headers, $name => $value]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function with(array $context): self
    {
        return new self($this->template, [...$this->context, ...$context], $this->status, $this->headers);
    }
}
