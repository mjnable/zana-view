<?php

declare(strict_types=1);

namespace Zana\View\Tests;

use PHPUnit\Framework\TestCase;
use Zana\Core\Application;
use Zana\Filesystem\FilesystemServiceProvider;
use Zana\Http\HttpServiceProvider;
use Zana\Media\MediaLibrary;
use Zana\Media\MediaServiceProvider;
use Zana\Routing\RoutingServiceProvider;
use Zana\Security\SecurityServiceProvider;
use Zana\View\Assets;
use Zana\View\PageMeta;
use Zana\View\ViewHelpers;
use Zana\View\ViewServiceProvider;

final class ContentHelpersTest extends TestCase
{
    private const string PUBLIC = __DIR__ . '/app/public';

    public function testVersionedAssetUrls(): void
    {
        $mtime = base_convert((string) filemtime(self::PUBLIC . '/assets/app.css'), 10, 36);

        $this->assertSame("/shop/assets/app.css?v={$mtime}", (new Assets(self::PUBLIC, '/shop'))->url('/assets/app.css'));
        $this->assertMatchesRegularExpression('#^/assets/app\.css\?v=[0-9a-f]{12}$#', (new Assets(self::PUBLIC, strategy: 'hash'))->url('assets/app.css'));
        $this->assertSame('/assets/missing.css', (new Assets(self::PUBLIC))->url('assets/missing.css'));
        $this->assertSame('/build/assets/app-4f3c2a.js', (new Assets(self::PUBLIC, strategy: 'manifest', manifestFile: 'build/.vite/manifest.json'))->url('src/app.js'));
    }

    public function testAssetPathsCannotClimbOut(): void
    {
        $this->expectExceptionMessage('Invalid asset path');
        (new Assets(self::PUBLIC))->url('../config/app.php');
    }

    public function testPageMetaTags(): void
    {
        $meta = (new PageMeta('https://acme.example', 'Acme', static fn (): string => 'fr-CA'))
            ->title('Tom & Jerry')
            ->description('A "quoted" page')
            ->canonical('/fr/a-propos')
            ->alternates(['en' => '/en/about', 'fr' => '/fr/a-propos'], default: 'en')
            ->image('/media/team.jpg', 'The team')
            ->jsonLd(['@type' => 'Article', 'headline' => '</script><script>alert(1)</script>']);

        $html = (string) $meta;

        $this->assertStringContainsString('<title>Tom &amp; Jerry | Acme</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="A &quot;quoted&quot; page">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://acme.example/fr/a-propos">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="https://acme.example/en/about">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="https://acme.example/en/about">', $html);
        $this->assertStringContainsString('<meta property="og:locale" content="fr_CA">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="https://acme.example/media/team.jpg">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertStringNotContainsString('</script><script>', $html, 'content cannot close the JSON-LD element');
        $this->assertStringContainsString('</script>', $html);
    }

    public function testHelpersForHoneypotsAssetsPicturesAndMeta(): void
    {
        $container = Application::configure(__DIR__ . '/app')
            ->withEnvironment('testing')
            ->withConfiguration([
                'security.secret' => str_repeat('s', 32),
                'security.csrf.honeypot' => 'website',
                'app.url' => 'https://acme.example',
                'filesystem.default' => 'public',
                'filesystem.disks' => ['public' => ['driver' => 'memory', 'url' => 'https://cdn.acme.example'], 'private' => ['driver' => 'memory']],
                'media.disk' => 'public',
                'media.private_disk' => 'private',
                'media.widths' => [200],
            ])
            ->withProviders(HttpServiceProvider::class, RoutingServiceProvider::class, SecurityServiceProvider::class, FilesystemServiceProvider::class, MediaServiceProvider::class, ViewServiceProvider::class)
            ->build()
            ->container();

        $helpers = $container->get(ViewHelpers::class);
        $library = $container->get(MediaLibrary::class);
        assert($helpers instanceof ViewHelpers && $library instanceof MediaLibrary);

        $field = (string) $helpers->call('csrf_field', ['contact']);
        $this->assertStringContainsString('<div hidden aria-hidden="true"><input name="website" value="" tabindex="-1" autocomplete="off"></div>', $field);
        $this->assertStringStartsWith('/assets/app.css?v=', (string) $helpers->call('asset', ['assets/app.css']));

        $image = imagecreatetruecolor(400, 200);
        ob_start();
        imagejpeg($image);
        $media = $library->store((string) ob_get_clean(), ['en' => 'A banner']);
        $picture = (string) $helpers->call('picture', [$media->toArray(), '50vw']);
        $this->assertStringContainsString('srcset="https://cdn.acme.example/media/' . $media->id . '/200.avif 200w"', $picture);
        $this->assertStringContainsString('alt="A banner"', $picture);
        $this->assertTrue($helpers->isSafe('picture'));

        $scope = $container->createScope();
        $meta = $scope->get(PageMeta::class);
        assert($meta instanceof PageMeta);
        $meta->title('Home')->canonical('/en');
        $views = $scope->get(\Zana\View\ViewRenderer::class);
        assert($views instanceof \Zana\View\ViewRenderer);
        $this->assertStringContainsString('<link rel="canonical" href="https://acme.example/en">', $views->render('meta'));
        $scope->dispose();
    }
}
