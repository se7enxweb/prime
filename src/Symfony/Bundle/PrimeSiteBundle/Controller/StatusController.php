<?php

/*
 * This file is part of the prime package.
 * (c) 2004-2026 7x <info@se7enx.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\PrimeSiteBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Status controller for the prime default site application.
 *
 * Symfony2 equivalent of apps/site/modules/status/actions/actions.class.php
 * from the primer symfony1 project.
 *
 * symfony1 → Symfony2 mapping
 * ─────────────────────────────────────────────────────────────────────────────
 *   statusActions extends sfMicroAction   → StatusController extends Controller
 *   executeHomepage(): string             → homepageAction(): RedirectResponse
 *   executeIndex(): string                → indexAction(): Response
 *   $this->varName = $value              → render(..., ['varName' => $value])
 *   return sfView::SUCCESS               → return $this->render(...)
 *   return sfView::NONE (after redirect) → return new RedirectResponse(...)
 *   SF_ROOT_DIR                          → kernel.root_dir/../
 *   $sf_app / $sf_env / $sf_debug        → container parameters + explicit pass
 *   indexSuccess.php                     → Resources/views/Status/index.html.twig
 *
 * @see apps/site/modules/status/actions/actions.class.php (primer symfony1)
 * @author 7x <info@se7enx.com>
 */
class StatusController extends Controller
{
    /**
     * Homepage action — 301 permanent redirect to /version.
     *
     * symfony1 equivalent: statusActions::executeHomepage()
     * In primer: $this->redirect('/version', 301) + return sfView::NONE.
     *
     * In Symfony2 a redirect is an explicit RedirectResponse rather than
     * a side-effecting method that sets headers and returns sfView::NONE.
     *
     * Route: GET /
     *
     * @return RedirectResponse
     */
    public function homepageAction()
    {
        return $this->redirect($this->generateUrl('prime_site_status'), 301);
    }

    /**
     * Status / version page action.
     *
     * symfony1 equivalent: statusActions::executeIndex()
     * In primer this method assigns $this->varName properties which arrive
     * in indexSuccess.php as local variables. In Symfony2 the same data is
     * passed explicitly as the second argument to render().
     *
     * Route: GET /version
     * Template: Resources/views/Status/index.html.twig  (≙ indexSuccess.php)
     *
     * @return Response
     */
    public function indexAction()
    {
        $rootDir = $this->container->getParameter('kernel.root_dir');
        $env     = $this->container->getParameter('kernel.environment');
        $debug   = $this->container->getParameter('kernel.debug');

        // symfony1: $sf_app is the application name ('site' in primer).
        // Symfony2 has no direct equivalent; we pass it as a template variable
        // to keep the same sf_app variable available in the Twig template.
        $appName = 'site';

        // ── Environment info ───────────────────────────────────────────────
        // symfony1: $this->phpVersion, $this->sfVersion, etc.
        $phpVersion = PHP_VERSION;
        $sfVersion  = '2.9-dev (prime — PHP 8.x compatible fork of Symfony 2.8)';

        // kernel.root_dir now IS the project root (see PrimeKernel::getRootDir()).
        // Unlike a conventional Symfony2 app where the kernel lives in app/ and
        // dirname(kernel.root_dir) gives the project root, PrimeKernel overrides
        // getRootDir() to return the project root directly.
        $gitBase   = $rootDir;
        $gitBranch = trim(
            shell_exec('git -C ' . escapeshellarg($gitBase) . ' rev-parse --abbrev-ref HEAD 2>/dev/null') ?: 'unknown'
        );
        $gitCommit = trim(
            shell_exec('git -C ' . escapeshellarg($gitBase) . ' log -1 --format="%h %s" 2>/dev/null') ?: 'unknown'
        );

        // ── Composer integration ───────────────────────────────────────────
        // symfony1 equivalent: composerJson, composerInstalled, composerPackages
        $composerJsonPath  = $gitBase . '/composer.json';
        $composerLockPath  = $gitBase . '/composer.lock';
        $composerVendor    = $gitBase . '/vendor/autoload.php';
        $composerInstalled = is_file($composerVendor);
        $composerJson      = is_file($composerJsonPath);
        $composerPackages  = [];

        if ($composerInstalled && is_file($composerLockPath)) {
            $lock             = json_decode(file_get_contents($composerLockPath), true);
            $composerPackages = array_column($lock['packages'] ?? [], 'version', 'name');
        }

        // ── Core class availability checks ─────────────────────────────────
        // Verifies the Symfony2 autoloader correctly resolves key framework
        // classes. Mirrors the $this->checks array in statusActions::executeIndex()
        // from primer but adapted for Symfony2 class names.
        $checks = [
            'Symfony\Component\HttpKernel\Kernel'                      => class_exists('Symfony\Component\HttpKernel\Kernel'),
            'Symfony\Component\HttpFoundation\Request'                 => class_exists('Symfony\Component\HttpFoundation\Request'),
            'Symfony\Component\Routing\Router'                         => class_exists('Symfony\Component\Routing\Router'),
            'Symfony\Component\DependencyInjection\ContainerBuilder'   => class_exists('Symfony\Component\DependencyInjection\ContainerBuilder'),
            'Symfony\Component\EventDispatcher\EventDispatcher'        => class_exists('Symfony\Component\EventDispatcher\EventDispatcher'),
            'Symfony\Component\Form\Form'                              => class_exists('Symfony\Component\Form\Form'),
            'Symfony\Component\Yaml\Yaml'                              => class_exists('Symfony\Component\Yaml\Yaml'),
            'Symfony\Component\Finder\Finder'                          => class_exists('Symfony\Component\Finder\Finder'),
        ];

        $allOk = !in_array(false, $checks, true);
        $title = 'prime 2.9 — Test Installation';

        // ── Render ──────────────────────────────────────────────────────────
        // symfony1: return sfView::SUCCESS → renders indexSuccess.php
        // Symfony2: return $this->render() → renders Status/index.html.twig
        //
        // All keys in the second argument arrive in the Twig template as
        // variables — the same contract as $this->varName in symfony1.
        return $this->render('PrimeSiteBundle:Status:index.html.twig', [
            // action-specific vars (mirror of $this->* in statusActions::executeIndex)
            'phpVersion'        => $phpVersion,
            'sfVersion'         => $sfVersion,
            'gitBranch'         => $gitBranch,
            'gitCommit'         => $gitCommit,
            'checks'            => $checks,
            'allOk'             => $allOk,
            'composerJson'      => $composerJson,
            'composerInstalled' => $composerInstalled,
            'composerPackages'  => $composerPackages,
            'title'             => $title,
            // symfony1 global sf_* vars — passed explicitly in Symfony2
            'sf_app'            => $appName,
            'sf_env'            => $env,
            'sf_debug'          => $debug,
            'sf_route'          => 'prime_site_status',
        ]);
    }

    /**
     * Boot-error page preview action — visual preview of the 503 fallback.
     *
     * Renders the same HTML as the pre-kernel prime_boot_error() fallback
     * using synthetic exception data, so you can inspect its appearance without
     * breaking the live site.
     *
     * Two scenarios are available via the ?scenario= query parameter:
     *   cache   — cache-permission error (shows chown/chmod remediation hint)
     *   generic — any other boot failure  (exception class + message only)
     *
     * Route: GET /503        (dev only — not registered in prod routing)
     *
     * @param  Request  $request
     * @return Response
     */
    public function previewBootErrorAction(Request $request): Response
    {
        $scenario = $request->query->get('scenario', 'cache');

        switch ($scenario) {
            case 'generic':
                $exception = new \RuntimeException(
                    'Call to undefined method Symfony\Component\DependencyInjection'
                    . '\Container::get() with argument type mismatch.'
                );
                break;

            case 'cache':
            default:
                $scenario  = 'cache';
                $exception = new \RuntimeException(
                    'Unable to create the cache directory'
                    . ' (/var/www/vhosts/alpha.se7enx.com/doc/symfony.alpha.se7enx.com'
                    . '/symfony/var/cache/prod/twig/c7).'
                );
                break;
        }

        require_once dirname(__DIR__) . '/Resources/boot_error.php';

        ob_start();
        prime_boot_error($exception, false);
        $html = ob_get_clean();

        $banner = '<div style="background:#fff3cd;border-bottom:2px solid #ffc107;'
            . 'padding:10px 24px;font-family:sans-serif;font-size:.85rem;'
            . 'display:flex;align-items:center;gap:16px">'
            . '<strong style="color:#856404">⚑ Preview mode</strong>'
            . '<span style="color:#666">Scenario: <strong>'
            . htmlspecialchars($scenario, ENT_QUOTES, 'UTF-8')
            . '</strong></span>'
            . '<span style="color:#888">—</span>'
            . '<a href="?scenario=cache" style="color:#0d6efd;text-decoration:none">Cache-permission error</a>'
            . '<a href="?scenario=generic" style="color:#0d6efd;text-decoration:none">Generic error</a>'
            . '</div>';

        $html = str_replace('<body>', '<body>' . "\n" . $banner, (string) $html);

        return new Response($html, Response::HTTP_OK, [
            'Content-Type'  => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
            'X-Robots-Tag'  => 'noindex, nofollow',
        ]);
    }
}
