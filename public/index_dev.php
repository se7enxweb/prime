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
 * prime 2.9 — development web front controller.
 *
 * Identical to index.php but boots the kernel with debug=true and env=dev,
 * which enables:
 *   - the Symfony2 web debug toolbar (WebProfilerBundle)
 *   - Twig strict_variables (undefined variable warnings become exceptions)
 *   - verbose exception pages instead of generic error responses
 *   - cache invalidation on every request (no stale-cache surprises)
 *
 * symfony1 equivalent: a separate frontend_dev.php front controller that
 * sets SF_ENV='dev' and SF_DEBUG=true, e.g.:
 *
 *   define('SF_ENV',   'dev');
 *   define('SF_DEBUG', true);
 *   new sfMicroDispatcher(SF_APP, SF_ENV, SF_DEBUG)->dispatch();
 *
 * SECURITY: This file must NEVER be accessible on a production server.
 * The IP restriction check below mirrors the guard added to the standard
 * Symfony2 app_dev.php to prevent accidental public exposure.
 */

// ── Autoloader ───────────────────────────────────────────────────────────────
// Loaded first so the Symfony YAML component is available for the access guard.
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Bundle\PrimeSiteBundle\PrimeKernel;
use Symfony\Component\Debug\Debug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

// ── Access guard ─────────────────────────────────────────────────────────────
// Allowed IPs are loaded from config/dev_access.yml — edit that file to add
// your IP without touching PHP code.  127.0.0.1 and ::1 are always permitted
// as a hard-coded fallback even if the YAML file is missing.
$accessConfig = __DIR__ . '/../config/dev_access.yml';
if (is_file($accessConfig)) {
    $allowedIps = Yaml::parse(file_get_contents($accessConfig))['allowed_ips'] ?? ['127.0.0.1', '::1'];
} else {
    $allowedIps = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];
}

// REMOTE_ADDR is 127.0.0.1 when requests arrive through the local nginx reverse
// proxy (nginx → Apache on 7081).  In that case X-Forwarded-For is set by our
// own trusted nginx, not by the client — so we treat the connection as local and
// skip the forwarded-IP check.  Only block HTTP_CLIENT_IP even from localhost
// because that header is always client-controlled.
$remoteIsLocal = \in_array($_SERVER['REMOTE_ADDR'], $allowedIps, true)
              || PHP_SAPI === 'cli-server';

if (isset($_SERVER['HTTP_CLIENT_IP'])
    || (!$remoteIsLocal && isset($_SERVER['HTTP_X_FORWARDED_FOR']))
    || !$remoteIsLocal
) {
    header('HTTP/1.0 403 Forbidden');
    exit('You are not allowed to access this file. Check ' . basename(__FILE__) . ' for more information.');
}

// ── Enable the Symfony2 debug component ──────────────────────────────────────
// symfony1: sfContext with SF_DEBUG=true automatically enables the debug bar
//           and routes exceptions to the debug exception handler.
// Symfony2: Debug::enable() registers an error handler that converts PHP
//           errors and warnings into exceptions, and an exception handler
//           that renders a readable debug page.
Debug::enable();

// ── Kernel bootstrap (dev) ───────────────────────────────────────────────────
$kernel = new PrimeKernel('dev', true);

// ── Request / Response cycle ─────────────────────────────────────────────────
$request  = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
