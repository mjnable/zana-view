<?php

declare(strict_types=1);

namespace Zana\View\Tests;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Application;
use Zana\Core\Module;
use Zana\Http\Exception\NotFoundHttpException;
use Zana\Http\HttpKernel;
use Zana\Http\HttpServiceProvider;
use Zana\Http\SecurityHeadersMiddleware;
use Zana\Routing\Router;
use Zana\Routing\RoutingServiceProvider;
use Zana\Security\CsrfTokens;
use Zana\Security\SecurityServiceProvider;
use Zana\Translation\LocaleMiddleware;
use Zana\Translation\TranslationServiceProvider;
use Zana\View\Markup;
use Zana\View\PhpEngine;
use Zana\View\TemplatePaths;
use Zana\View\View;
use Zana\View\ViewException;
use Zana\View\ViewHelpers;
use Zana\View\ViewRenderer;
use Zana\View\ViewServiceProvider;

final class ViewTest extends TestCase
{
    private ContainerInterface $container;

    private HttpKernel $kernel;

    protected function setUp(): void
    {
        $this->container = Application::configure(__DIR__ . '/app')
            ->withEnvironment('testing')
            ->withConfiguration([
                'translation.locales' => ['en', 'fr'],
                'security.secret' => str_repeat('s', 32),
                'view.globals' => ['site' => 'Zana & Co'],
                'http.middleware' => [SecurityHeadersMiddleware::class, LocaleMiddleware::class],
                'http.security_headers.csp' => ['script-src' => ["'self'", '{nonce}']],
            ])
            ->withProviders(HttpServiceProvider::class, RoutingServiceProvider::class, TranslationServiceProvider::class, SecurityServiceProvider::class, ViewServiceProvider::class)
            ->withModules(ContactModule::class)
            ->build()
            ->container();

        $router = $this->container->get(Router::class);
        assert($router instanceof Router);
        $router->localized(['en' => '/', 'fr' => '/'], static fn (): string => 'home')->name('home');
        $router->localized(['en' => '/about', 'fr' => '/a-propos'], static fn (): View => View::make('page', [
            'heading' => 'Tom & <Jerry>',
            'description' => 'A "quoted" page',
            'items' => ['<b>one</b>', 'two'],
            'intro' => new Markup('<em>safe</em>'),
            'author' => new Author(),
        ])->withHeader('X-Page', 'about'))->name('about');
        $router->localized(['en' => '/contact', 'fr' => '/contact'], static fn (): View => View::make('@Contact/form')->withStatus(202))->name('contact');
        $router->get('/script', static fn (): View => View::make('script'));
        $router->get('/gone', static fn () => throw new NotFoundHttpException('This page has moved <away>.'));

        $kernel = $this->container->get(HttpKernel::class);
        assert($kernel instanceof HttpKernel);
        $this->kernel = $kernel;
    }

    public function testViewsRenderInsideTheirLayoutWithEscapedContext(): void
    {
        $response = $this->get('/en/about');
        $html = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('about', $response->getHeaderLine('X-Page'));
        $this->assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('<title>About | Site</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="A &quot;quoted&quot; page">', $html, 'a section filled by the child');
        $this->assertStringContainsString('<h1>Tom &amp; &lt;Jerry&gt;</h1>', $html);
        $this->assertStringContainsString('<li>&lt;b&gt;one&lt;/b&gt;</li>', $html, 'strings inside arrays too');
        $this->assertStringContainsString('<p><em>safe</em></p>', $html, 'Markup is not escaped again');
        $this->assertStringContainsString('<p class="raw">Tom & <Jerry></p>', $html);
        $this->assertStringContainsString('<p class="object">&lt;Ada&gt;</p>', $html);
        $this->assertStringContainsString('<main>', $html);
    }

    public function testHelpersFollowTheRequestLocale(): void
    {
        $html = (string) $this->get('/fr/a-propos')->getBody();

        $this->assertStringContainsString('<html lang="fr">', $html, 'the locale global');
        $this->assertStringContainsString('<nav><a href="/fr">Accueil</a></nav>', $html, 'url() and t() in the current locale');
    }

    public function testModuleTemplatesAndTheCsrfField(): void
    {
        $response = $this->get('/fr/contact');
        $html = (string) $response->getBody();

        $this->assertSame(202, $response->getStatusCode());
        $this->assertStringContainsString('<button>Envoyer &lt;vite&gt;</button>', $html, 'helper output is escaped');
        $this->assertSame(1, preg_match('/<input type="hidden" name="_token" value="([^"]+)"><input type="hidden" name="_form" value="contact">/', $html, $match));

        $tokens = $this->container->get(CsrfTokens::class);
        assert($tokens instanceof CsrfTokens);
        $this->assertTrue($tokens->verify($match[1] ?? '', 'contact'));
    }

    public function testTheCspNonceReachesTemplates(): void
    {
        $response = $this->get('/script');

        $this->assertSame(1, preg_match('/<script nonce="([^"]+)">/', (string) $response->getBody(), $match));
        $nonce = $match[1] ?? '';
        $this->assertStringContainsString("'nonce-{$nonce}'", $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testErrorPagesUseTemplates(): void
    {
        $response = $this->get('/gone');
        $html = (string) $response->getBody();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('<title>Not Found | Site</title>', $html);
        $this->assertStringContainsString('<p class="error">404: This page has moved &lt;away&gt;.</p>', $html);
    }

    public function testErrorPagesRenderInTheRequestLocale(): void
    {
        $html = (string) $this->get('/fr/nulle-part')->getBody();

        $this->assertStringContainsString('<html lang="fr">', $html);
        $this->assertStringContainsString('<nav><a href="/fr">Accueil</a></nav>', $html);
    }

    public function testStatusesWithoutTemplatesFallBackToTheBuiltInPage(): void
    {
        $response = $this->kernel->handle(new ServerRequest('DELETE', '/en/about', ['Accept' => 'text/html']));

        $this->assertSame(405, $response->getStatusCode());
        $this->assertStringContainsString('<h1>405 Method Not Allowed</h1>', (string) $response->getBody());
    }

    public function testJsonClientsKeepProblemDocuments(): void
    {
        $response = $this->kernel->handle(new ServerRequest('GET', '/gone', ['Accept' => 'application/json']));

        $this->assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function testGlobalsAreAvailableAndContextWins(): void
    {
        $scope = $this->container->createScope();
        $views = $scope->get(ViewRenderer::class);
        assert($views instanceof ViewRenderer);

        $this->assertSame('Zana &amp; Co', $views->render('globals'));
        $this->assertSame('Mine', $views->render('globals', ['site' => 'Mine']));
        $scope->dispose();
    }

    public function testInvalidAndMissingTemplatesAreReported(): void
    {
        $engine = new PhpEngine((new TemplatePaths())->add(__DIR__ . '/app/templates'), new ViewHelpers());

        foreach (['../secret', 'pages/../../x', "a\0b", '@NoSlash'] as $name) {
            try {
                $engine->render($name, []);
                $this->fail("Expected {$name} to be rejected.");
            } catch (ViewException $e) {
                $this->assertStringStartsWith('Invalid template name', $e->getMessage());
            }
        }

        $this->assertFalse($engine->exists('nope'));
        $this->expectExceptionMessage('The template "nope" does not exist.');
        $engine->render('nope', []);
    }

    public function testValuesPassedToLayoutsAndPartialsAreEscapedOnce(): void
    {
        $helpers = (new ViewHelpers())->add('upper', static fn (string $text): string => strtoupper($text));
        $engine = new PhpEngine((new TemplatePaths())->add(__DIR__ . '/app/templates'), $helpers);

        $this->assertSame("<h1>Tom &amp; Jerry</h1><h2>TOM &amp; JERRY</h2><p>Tom &amp; Jerry</p>\n", $engine->render('passes', ['name' => 'Tom & Jerry']));
    }

    public function testHelpersReturningArraysHaveTheirStringsEscaped(): void
    {
        $helpers = (new ViewHelpers())->add('links', static fn (): array => ['en' => '/en?a=1&b=2', 'fr' => '<x>']);
        $engine = new PhpEngine((new TemplatePaths())->add(__DIR__ . '/app/templates'), $helpers);

        $this->assertSame('en=/en?a=1&amp;b=2;fr=&lt;x&gt;;', $engine->render('links', []));
    }

    public function testFailuresInsideTemplatesCleanTheirOutputBuffers(): void
    {
        $engine = new PhpEngine((new TemplatePaths())->add(__DIR__ . '/app/templates'), new ViewHelpers());
        $level = ob_get_level();

        try {
            $engine->render('broken', []);
            $this->fail('Expected a ViewException.');
        } catch (ViewException $e) {
            $this->assertSame('Template broken: section "head" is not closed with stop().', $e->getMessage());
        }

        $this->assertSame($level, ob_get_level());
    }

    private function get(string $path): ResponseInterface
    {
        return $this->kernel->handle(new ServerRequest('GET', $path, ['Accept' => 'text/html']));
    }
}

final class Author
{
    public function __toString(): string
    {
        return '<Ada>';
    }
}

final class ContactModule extends Module
{
    public function name(): string
    {
        return 'Contact';
    }

    public function path(string ...$segments): string
    {
        return implode('/', [__DIR__ . '/app/modules/Contact', ...$segments]);
    }

    public function register(ServiceCollectionInterface $services): void
    {
    }
}
