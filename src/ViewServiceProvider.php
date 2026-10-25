<?php

declare(strict_types=1);

namespace Zana\View;

use Zana\Configuration\ConfigurationInterface;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Container\Contracts\ServiceResolverInterface;
use Zana\Core\ApplicationInterface;
use Zana\Core\ServiceProvider;
use Zana\Http\ErrorRendererInterface;
use Zana\Http\RequestContext;
use Zana\Routing\ResultConverterInterface;
use Zana\Routing\UrlGenerator;
use Zana\Security\CsrfTokens;
use Zana\Translation\LocaleContext;
use Zana\Translation\TranslatorInterface;

/**
 * Registers the template folders, the helpers, the native engine, the
 * renderer (scoped), View route results and the error renderer (unless the
 * application registered one). Reads view.path and view.globals.
 *
 * At boot, view.path (default <base>/templates) is added without a
 * namespace, then each module's Resources/views folder under the module's
 * name: "@Contact/form". Helpers: url(), alternates(); with
 * mj-zana/translation, t() and the "locale" global; with mj-zana/security,
 * csrf_token() and csrf_field().
 */
final class ViewServiceProvider extends ServiceProvider
{
    public function register(ServiceCollectionInterface $services): void
    {
        $services
            ->singleton(TemplatePaths::class)
            ->singleton(ViewHelpers::class, static function (ServiceResolverInterface $r): ViewHelpers {
                $helpers = new ViewHelpers();
                $urls = $r->get(UrlGenerator::class);
                assert($urls instanceof UrlGenerator);
                $helpers
                    ->add('url', $urls->generate(...))
                    ->add('alternates', $urls->alternates(...));

                if (interface_exists(TranslatorInterface::class) && $r->has(TranslatorInterface::class)) {
                    $translator = $r->get(TranslatorInterface::class);
                    $locale = $r->get(LocaleContext::class);
                    assert($translator instanceof TranslatorInterface && $locale instanceof LocaleContext);
                    $helpers->add('t', $translator->translate(...))->addGlobal('locale', $locale->get(...));
                }

                if (class_exists(CsrfTokens::class) && $r->has(CsrfTokens::class)) {
                    $csrf = $r->get(CsrfTokens::class);
                    assert($csrf instanceof CsrfTokens);
                    $helpers
                        ->add('csrf_token', $csrf->issue(...))
                        ->add('csrf_field', static fn (string $form = CsrfTokens::DEFAULT_FORM): Markup => new Markup(sprintf(
                            '<input type="hidden" name="%s" value="%s"><input type="hidden" name="%s" value="%s">',
                            CsrfTokens::FIELD,
                            htmlspecialchars($csrf->issue($form), ENT_QUOTES, 'UTF-8'),
                            CsrfTokens::FORM_FIELD,
                            htmlspecialchars($form, ENT_QUOTES, 'UTF-8'),
                        )), safe: true);
                }

                return $helpers;
            })
            ->trySingleton(EngineInterface::class, PhpEngine::class)
            ->scoped(ViewRenderer::class, static function (ServiceResolverInterface $r): ViewRenderer {
                $engine = $r->get(EngineInterface::class);
                $helpers = $r->get(ViewHelpers::class);
                $request = $r->get(RequestContext::class);
                assert($engine instanceof EngineInterface && $helpers instanceof ViewHelpers && $request instanceof RequestContext);

                return new ViewRenderer($engine, $helpers, $r->get(ConfigurationInterface::class)->array('view.globals', []), $request);
            })
            ->alias(ViewRendererInterface::class, ViewRenderer::class)
            ->scoped(ViewResultConverter::class)
            ->tag(ResultConverterInterface::TAG, ViewResultConverter::class);

        if (!$services->has(ErrorRendererInterface::class)) {
            $services->scoped(ErrorRendererInterface::class, ViewErrorRenderer::class);
        }
    }

    public function boot(ContainerInterface $container): void
    {
        $application = $container->get(ApplicationInterface::class);
        $paths = $container->get(TemplatePaths::class);
        assert($application instanceof ApplicationInterface && $paths instanceof TemplatePaths);

        $paths->add($application->config()->string('view.path', $application->path('templates')));
        foreach ($application->modules() as $module) {
            $folder = $module->path('Resources', 'views');
            if (is_dir($folder)) {
                $paths->add($folder, $module->name());
            }
        }
    }

    public function origin(): string
    {
        return 'package:mj-zana/view';
    }
}
