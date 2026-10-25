<?php

declare(strict_types=1);

namespace Zana\View;

use Zana\View\Internal\PhpTemplate;

/**
 * The native engine: templates are PHP files ending in ".php".
 *
 * Context strings, and strings inside arrays, are escaped before the
 * template sees them; Markup, numbers, booleans and null pass unchanged, and
 * objects must be escaped by the template with $this->e(). Inside a
 * template, $this offers e(), raw(), layout(), start()/stop(), section(),
 * insert(), and every view helper by name.
 *
 *     <?php $this->layout('layouts/site', ['title' => 'About']) ?>
 *     <h1><?= $title ?></h1>
 *     <a href="<?= $this->url('contact') ?>"><?= $this->t('nav.contact') ?></a>
 */
final readonly class PhpEngine implements EngineInterface
{
    public const string EXTENSION = '.php';

    public function __construct(
        private TemplatePaths $paths,
        private ViewHelpers $helpers,
    ) {
    }

    public function render(string $template, array $context): string
    {
        return (new PhpTemplate($this, $this->helpers, $template, $context))->render();
    }

    public function exists(string $template): bool
    {
        return $this->paths->exists($template, self::EXTENSION);
    }

    /**
     * @internal Used by templates.
     */
    public function file(string $template): string
    {
        return $this->paths->find($template, self::EXTENSION);
    }
}
