<?php

/*
 * This file is part of the prime package.
 * (c) 2004-2026 7x <info@se7enx.com>
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * prime 2.9 — production web front controller.
 *
 * This is the single HTTP entry point for all requests in production
 * (debug=false, env=prod). The Apache .htaccess rewrites every request that
 * does not match a real file/directory to this file.
 *
 * symfony1 primer front controller (public/index.php) → Symfony2 mapping
 * ─────────────────────────────────────────────────────────────────────────────
 *   define('SF_ROOT_DIR', realpath(__DIR__).'/../')
 *     → __DIR__.'/..' (implicit via kernel.root_dir / Composer autoload path)
 *
 *   define('SF_APP', 'site')
 *     → PrimeSiteBundle (registered in PrimeKernel::registerBundles())
 *
 *   define('SF_ENV', 'prod') / define('SF_DEBUG', false)
 *     → new PrimeKernel('prod', false)
 *
 *   sfCoreAutoload::register()
 *     → require __DIR__.'/../vendor/autoload.php'  (Composer PSR-4)
 *
 *   new sfMicroDispatcher(SF_APP, SF_ENV, SF_DEBUG)->dispatch()
 *     → $kernel->handle($request)->send() + $kernel->terminate(...)
 *
 * The APC / OpCache check in the original Symfony2 app.php template is
 * omitted here — PHP 8.x ships with OPcache built-in and it is handled
 * transparently without runtime intervention.
 */

// ── Autoloader ───────────────────────────────────────────────────────────────
// Composer's PSR-4 autoloader resolves all Symfony\Bundle\*,
// Symfony\Component\*, and se7enxweb\* classes without an explicit require.
//
// public/index.php lives one level below the project root, so the vendor/
// directory is at __DIR__.'/../vendor/'.
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Bundle\PrimeSiteBundle\PrimeKernel;
use Symfony\Component\HttpFoundation\Request;

// ── Boot-failure fallback ─────────────────────────────────────────────────────
// Catches any \Throwable thrown during kernel boot or request handling and
// renders a self-contained HTML 503 page.  No Twig, no assets, no cache — so
// it works even when the filesystem/cache layer is the thing that broke.
require_once __DIR__ . '/../src/Symfony/Bundle/PrimeSiteBundle/Resources/boot_error.php';

// ── Kernel bootstrap ─────────────────────────────────────────────────────────
// symfony1: $configuration = ProjectConfiguration::getApplicationConfiguration(
//               'site', 'prod', false);
//           sfContext::createInstance($configuration)->dispatch();
//
// Symfony2: instantiate the kernel for the requested environment, then let
// it handle the request and send the response.
try {
    $kernel = new PrimeKernel('prod', false);

    // ── Request / Response cycle ─────────────────────────────────────────────
    // symfony1: sfMicroDispatcher::dispatch() encapsulates all three steps.
    // Symfony2 makes the three phases explicit:
    //   1. createFromGlobals() — wraps PHP superglobals in a Request object
    //   2. handle()            — routing → controller → response (the kernel loop)
    //   3. send()              — writes status line, headers, and body to output
    $request  = Request::createFromGlobals();
    $response = $kernel->handle($request);
    $response->send();

    // ── Termination ──────────────────────────────────────────────────────────
    // Fires kernel.terminate listeners (e.g. e-mail spool flush, async logging).
    // symfony1 equivalent: sfContext::getInstance()->shutdown() / postDispatch.
    $kernel->terminate($request, $response);
} catch (\Throwable $e) {
    prime_boot_error($e);
}
