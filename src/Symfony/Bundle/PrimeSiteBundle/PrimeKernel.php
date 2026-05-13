<?php

/*
 * This file is part of the prime package.
 * (c) 2004-2026 7x <info@se7enx.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\PrimeSiteBundle;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\PrimeMigrateBundle\PrimeMigrateBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

/**
 * PrimeKernel — minimal Symfony2 micro-kernel for the default prime installation.
 *
 * Symfony2 equivalent of the primer symfony1 front-controller bootstrap.
 *
 * symfony1 primer bootstrap (public/index.php)          Symfony2 PrimeKernel
 * ──────────────────────────────────────────────────    ────────────────────────────────────────────
 * define('SF_ROOT_DIR', ...)                          → kernel.root_dir + __DIR__ calc
 * define('SF_APP', 'site')                            → bundle name 'PrimeSiteBundle'
 * define('SF_ENV', 'prod')                            → $environment constructor arg
 * define('SF_DEBUG', false)                           → $debug constructor arg
 * sfCoreAutoload::register()                          → Composer autoloader (vendor/autoload.php)
 * new sfMicroDispatcher(...)->dispatch()              → $kernel->handle($request)->send()
 * $routing->connect(...)  in routing.php              → configureRoutes() below
 * sfContext (container/service locator)               → ContainerBuilder in configureContainer()
 * apps/site/config/routing.php                        → @PrimeSiteBundle/Resources/config/routing.yml
 *
 * Bundle registration mirrors symfony1's application/module hierarchy:
 *   FrameworkBundle   — core HTTP kernel, router, controller resolver
 *   TwigBundle        — Twig template engine (replaces sfPHPView)
 *   PrimeSiteBundle   — status controller + default layout (apps/site equivalent)
 *
 * Cache and log directories use sys_get_temp_dir() so the project root stays
 * read-only — appropriate for a library package. Override getCacheDir() /
 * getLogDir() in a subclass when deploying as a full application.
 *
 * @see public/index.php     Production front controller
 * @see public/index_dev.php Development front controller
 * @author 7x <info@se7enx.com>
 */
class PrimeKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * Registers the bundles that constitute this application.
     *
     * symfony1 equivalent: ProjectConfiguration::setup() which calls
     * $this->enablePlugins() / $this->enableAllPluginsExcept().
     *
     * In Symfony2 every bundle is an independent component that can register
     * its own routes, services, commands, and templates.
     *
     * @return array
     */
    public function registerBundles()
    {
        $bundles = [
            new FrameworkBundle(),
            new MonologBundle(),
            new TwigBundle(),
            new PrimeSiteBundle(),
            new PrimeMigrateBundle(),
        ];

        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            $bundles[] = new \Symfony\Bundle\DebugBundle\DebugBundle();
            $bundles[] = new \Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
        }

        return $bundles;
    }

    /**
     * Registers routes for this application.
     *
     * symfony1 equivalent: apps/site/config/routing.php where
     * $routing->connect() calls add routes to sfPatternRouting.
     *
     * MicroKernelTrait calls this method during boot (via loadRoutes()) and
     * wraps the result in a service-type router resource — identical in effect
     * to a routing.yml import.
     *
     * @param RouteCollectionBuilder $routes
     */
    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        // Top-level routing file: config/routing.yml.
        // Falls back to the bundle's own routing.yml if the project-level file
        // does not exist yet (e.g. first boot before config/ is scaffolded).
        $projectRouting = $this->getRootDir() . '/config/routing.yml';
        if (is_file($projectRouting)) {
            $routes->import($projectRouting, '/');
        } else {
            // symfony1 equivalent: routing.php — $routing->connect('homepage', ...)
            //                                     $routing->connect('version', ...)
            $routes->import('@PrimeSiteBundle/Resources/config/routing.yml', '/');
        }

        // Dev-only: import WebProfilerBundle routes when in debug mode.
        if ($this->isDebug()) {
            $routes->import(
                '@WebProfilerBundle/Resources/config/routing/wdt.xml',
                '/_wdt'
            );
            $routes->import(
                '@WebProfilerBundle/Resources/config/routing/profiler.xml',
                '/_profiler'
            );
        }
    }

    /**
     * Configures the DI container (service definitions and extension config).
     *
     * symfony1 equivalent: apps/site/config/app.yml + factories.yml combined.
     * In symfony1 the framework was configured via YAML config files processed
     * by config handler classes; in Symfony2 the container builder processes
     * extension configuration directly.
     *
     * Key settings
     * ─────────────
     *   framework.secret       — HMAC / CSRF token seed (change in production)
     *   framework.templating   — registers the Twig engine (replaces sfPHPView)
     *   twig.debug             — enables Twig debug extensions in dev/test
     *   twig.strict_variables  — throws on undefined Twig vars in dev/test
     *
     * @param ContainerBuilder $c
     * @param LoaderInterface  $loader
     */
    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        // ── YAML config files ────────────────────────────────────────────────
        // Load config/config_{env}.yml when it exists (preferred — edit that
        // file to change settings).  Each env file begins with:
        //   imports:
        //       - { resource: config.yml }
        // so the base config.yml is always applied first.
        //
        // Falls back to hardcoded defaults when the config files are absent
        // (e.g. during first boot or in unit tests without the config/ dir).
        $envConfig = $this->getRootDir() . '/config/config_' . $this->environment . '.yml';
        $baseConfig = $this->getRootDir() . '/config/config.yml';

        if (is_file($envConfig)) {
            $loader->load($envConfig);
            return;
        }

        if (is_file($baseConfig)) {
            $loader->load($baseConfig);
            return;
        }

        // ── Built-in fallback defaults (no config/ files present) ────────────
        // symfony1 equivalent: apps/site/config/app.yml + factories.yml

        // 'secret' is the application secret used for CSRF tokens and signed
        // URIs. Override this value in config/config.yml before deploying.
        $c->loadFromExtension('framework', [
            'secret'     => 'change-me-before-deploying-to-production',
            'templating' => ['engines' => ['twig']],
        ]);

        // symfony1: sfPHPView handled PHP templates; Twig is the Symfony2 equiv.
        $c->loadFromExtension('twig', [
            'debug'            => $this->isDebug(),
            'strict_variables' => $this->isDebug(),
        ]);

        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            $c->loadFromExtension('web_profiler', [
                'toolbar'             => true,
                'intercept_redirects' => false,
            ]);
        }
    }

    /**
     * Returns the project root directory.
     *
     * Symfony2's default Kernel::getRootDir() returns the directory of the
     * kernel file itself (here: PrimeSiteBundle/). That is wrong for a library-
     * style bundle — it would make kernel.root_dir point deep inside src/.
     *
     * PrimeKernel.php lives at:
     *   {project-root}/src/Symfony/Bundle/PrimeSiteBundle/PrimeKernel.php
     * Traversing 5 levels up reaches {project-root} (the symfony/ directory
     * that contains composer.json, vendor/, public/, bin/, etc.).
     *
     * @return string
     */
    public function getRootDir()
    {
        // PrimeKernel.php lives at:
        //   {project-root}/src/Symfony/Bundle/PrimeSiteBundle/PrimeKernel.php
        // 4 levels up: PrimeSiteBundle/ → Bundle/ → Symfony/ → src/ → {project-root}
        return realpath(__DIR__ . '/../../../..');
    }

    /**
     * Returns the cache directory.
     *
     * Uses sys_get_temp_dir() so the project root can be read-only (suitable
     * for a Composer library package). Override when running as a full app.
     *
     * @return string
     */
    public function getCacheDir()
    {
        // Use a path under the project root so CLI and PHP-FPM share the same
        // cache location. sys_get_temp_dir() differs per process user because
        // systemd gives PHP-FPM its own private /tmp (PrivateTmp=true).
        return $this->getRootDir() . '/var/cache/' . $this->environment;
    }

    /**
     * Returns the log directory.
     *
     * @return string
     */
    public function getLogDir()
    {
        return $this->getRootDir() . '/var/log';
    }

    /**
     * Returns the kernel name shown in the Web Debug Toolbar and profiler.
     *
     * The default Kernel::getName() derives the name from basename($rootDir),
     * which would return 'symfony' (the project root directory name). We
     * override it to return 'prime' — the actual application name.
     *
     * @return string
     */
    public function getName()
    {
        return 'prime';
    }
}
