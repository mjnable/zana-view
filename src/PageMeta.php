<?php

declare(strict_types=1);

namespace Zana\View;

/**
 * The SEO metadata of the current page (FE-34), filled by the controller and
 * printed by the layout as the "meta" global:
 *
 *     $meta->title('About us')->description('…')
 *         ->canonical($urls->generate('about'))->alternates($urls->alternates('about'))
 *         ->jsonLd(['@type' => 'Organization', 'name' => 'Acme', 'url' => 'https://acme.example']);
 *
 *     <head><?= $meta ?></head>
 *
 * Relative URLs become absolute with app.url. JSON-LD is encoded so that
 * content can never close the <script> element. Registered scoped.
 */
final class PageMeta implements \Stringable
{
    private ?string $title = null;

    private ?string $description = null;

    private ?string $canonical = null;

    /** @var array<string, string> */
    private array $alternates = [];

    private ?string $image = null;

    private ?string $imageAlt = null;

    private string $type = 'website';

    private ?string $robots = null;

    /** @var list<array<string, mixed>> */
    private array $jsonLd = [];

    /**
     * @param (\Closure(): string)|null $locale The current locale, for og:locale
     */
    public function __construct(
        private readonly string $origin = '',
        private readonly ?string $siteName = null,
        private readonly ?\Closure $locale = null,
    ) {
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function canonical(string $url): self
    {
        $this->canonical = $this->absolute($url);

        return $this;
    }

    /**
     * @param array<string, string> $urls locale => URL, e.g. UrlGenerator::alternates()
     */
    public function alternates(array $urls, ?string $default = null): self
    {
        foreach ($urls as $locale => $url) {
            $this->alternates[$locale] = $this->absolute($url);
        }
        if ($default !== null && isset($urls[$default])) {
            $this->alternates['x-default'] = $this->absolute($urls[$default]);
        }

        return $this;
    }

    public function image(string $url, ?string $alt = null): self
    {
        $this->image = $this->absolute($url);
        $this->imageAlt = $alt;

        return $this;
    }

    /**
     * The Open Graph type: "website", or "article" for posts.
     */
    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function robots(string $robots): self
    {
        $this->robots = $robots;

        return $this;
    }

    /**
     * @param array<string, mixed> $data A schema.org object; @context is added
     */
    public function jsonLd(array $data): self
    {
        $this->jsonLd[] = ['@context' => 'https://schema.org', ...$data];

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Every tag, ready for <head>.
     */
    public function tags(): Markup
    {
        $e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $tags = [];

        if ($this->title !== null) {
            $full = $this->siteName !== null && $this->siteName !== $this->title ? "{$this->title} | {$this->siteName}" : $this->title;
            $tags[] = '<title>' . $e($full) . '</title>';
            $tags[] = '<meta property="og:title" content="' . $e($this->title) . '">';
        }
        if ($this->description !== null) {
            $tags[] = '<meta name="description" content="' . $e($this->description) . '">';
            $tags[] = '<meta property="og:description" content="' . $e($this->description) . '">';
        }
        if ($this->robots !== null) {
            $tags[] = '<meta name="robots" content="' . $e($this->robots) . '">';
        }
        if ($this->canonical !== null) {
            $tags[] = '<link rel="canonical" href="' . $e($this->canonical) . '">';
            $tags[] = '<meta property="og:url" content="' . $e($this->canonical) . '">';
        }
        foreach ($this->alternates as $locale => $url) {
            $tags[] = '<link rel="alternate" hreflang="' . $e($locale) . '" href="' . $e($url) . '">';
        }
        $tags[] = '<meta property="og:type" content="' . $e($this->type) . '">';
        if ($this->siteName !== null) {
            $tags[] = '<meta property="og:site_name" content="' . $e($this->siteName) . '">';
        }
        if ($this->locale !== null) {
            $tags[] = '<meta property="og:locale" content="' . $e(str_replace('-', '_', ($this->locale)())) . '">';
        }
        if ($this->image !== null) {
            $tags[] = '<meta property="og:image" content="' . $e($this->image) . '">';
            if ($this->imageAlt !== null) {
                $tags[] = '<meta property="og:image:alt" content="' . $e($this->imageAlt) . '">';
            }
            $tags[] = '<meta name="twitter:card" content="summary_large_image">';
        } else {
            $tags[] = '<meta name="twitter:card" content="summary">';
        }
        foreach ($this->jsonLd as $data) {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $tags[] = '<script type="application/ld+json">' . $json . '</script>';
        }

        return new Markup(implode("\n", $tags));
    }

    public function __toString(): string
    {
        return $this->tags()->html;
    }

    private function absolute(string $url): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) === 1) {
            return $url;
        }

        return rtrim($this->origin, '/') . '/' . ltrim($url, '/');
    }
}
