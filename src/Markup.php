<?php

declare(strict_types=1);

namespace Zana\View;

/**
 * HTML that is already safe, so templates print it without escaping it
 * again. Only wrap HTML you built or sanitised yourself.
 */
final readonly class Markup implements \Stringable
{
    public function __construct(public string $html)
    {
    }

    public function __toString(): string
    {
        return $this->html;
    }
}
