# mj-zana/view

HTML views for Zana applications: templates per module, layouts, escaping by
default, `View` route results and templated error pages. Templates are plain
PHP; `mj-zana/view-twig` adds Twig.

```
composer require mj-zana/view
```

## Setup

Register `ViewServiceProvider`, after the http and routing providers. Templates
live in `templates/` (`view.path`), and each module's `Resources/views/` is
available under the module's name:

```php
#[Route('/contact', methods: ['GET'])]
public function show(): View
{
    return View::make('@Contact/form', ['sent' => false]);
}
```

## Templates

```php
<?php $this->layout('layouts/site', ['title' => 'About']) ?>
<h1><?= $heading ?></h1>
<a href="<?= $this->url('contact') ?>"><?= $this->t('nav.contact') ?></a>
```

```php
<!-- templates/layouts/site.php -->
<html lang="<?= $locale ?>"><title><?= $title ?></title>
<?= $this->section('content') ?></html>
```

- **Escaping by default.** Strings in the context, also inside arrays, are
  escaped before the template sees them. Wrap trusted HTML in `Markup`.
  Objects are not escaped: print them with `$this->e($object)`.
  `$this->raw('name')` gives a value unescaped.
- **Passing values on.** Values a template passes to `layout()` or
  `insert()` are printed as given, since template variables and helper
  output are already escaped. Give helpers `$this->raw('name')` when they
  need the original text.
- **Layouts and sections.** `layout()`, `start()`/`stop()` and `section()`;
  `insert()` renders a partial with the current context.
- **Helpers.**
  - `url()` and `alternates()`, from routing;
  - `t()` and the `locale` global, with `mj-zana/translation`;
  - `csrf_token()` and `csrf_field()`, with `mj-zana/security`;
  - add your own with `ViewHelpers::add()`.

  Helper output is escaped unless the helper is declared safe.
- **Globals.** `view.globals`, `ViewHelpers::addGlobal()`, and `csp_nonce`
  when `SecurityHeadersMiddleware` uses a nonce.

## Error pages

Browsers get `errors/<status>`, e.g. `errors/404`, or `errors/error`, with
`status`, `title` and `detail`. Without those templates, they get the
built-in page. JSON clients still get problem documents.

## License

MIT
