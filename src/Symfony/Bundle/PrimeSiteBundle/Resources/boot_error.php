<?php

/*
 * This file is part of the prime package.
 * (c) 2004-2026 7x <info@se7enx.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * prime_boot_error — self-contained 503 fallback renderer.
 *
 * Called from public/index.php whenever a \Throwable escapes kernel boot or
 * the request/response cycle.  Intentionally uses zero dependencies (no Twig,
 * no Symfony services, no filesystem writes) so it works even when the thing
 * that broke is the cache or autoloader layer.
 *
 * Also required by public/_test_503.php for browser-based visual testing.
 *
 * @param \Throwable $e  The uncaught exception or error.
 */
function prime_boot_error(\Throwable $e, bool $exit = true): void
{
    $rawMsg  = $e->getMessage();
    $exClass = get_class($e);

    // Classify the failure so we can surface a targeted remediation hint.
    $isCachePerm = (
        str_contains($rawMsg, 'Unable to create') ||
        str_contains($rawMsg, 'Permission denied') ||
        str_contains($rawMsg, 'failed to open dir')
    ) && (str_contains($rawMsg, 'cache') || str_contains($rawMsg, '/var/'));

    // Determine the effective user/group of the web process for the hint.
    $procUser  = function_exists('posix_getpwuid')
        ? (posix_getpwuid(posix_geteuid())['name'] ?? '?')
        : '?';
    $procGroup = function_exists('posix_getgrgid')
        ? (posix_getgrgid(posix_getegid())['name'] ?? '?')
        : '?';

    // Strip the server-local path prefix so the message is shorter on screen.
    $displayMsg   = htmlspecialchars(
        preg_replace('#/var/www/vhosts/[^/]+/#', '/', $rawMsg) ?? $rawMsg,
        ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'
    );
    $displayClass = htmlspecialchars($exClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, no-cache');
        header('X-Robots-Tag: noindex, nofollow');
    }

    $u = htmlspecialchars($procUser,  ENT_QUOTES, 'UTF-8');
    $g = htmlspecialchars($procGroup, ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex,nofollow">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>503 — Service Temporarily Unavailable</title>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,sans-serif;background:#f5f5f5;color:#333;min-height:100vh}
        header{background:#fff;border-bottom:1px solid #e0e0e0;padding:14px 24px}
        .logo{font-size:1.2em;font-weight:700;color:#1a1a1a;letter-spacing:-0.4px}
        main{max-width:640px;margin:60px auto 40px;padding:0 24px}
        .status{font-size:5rem;font-weight:800;color:#d0d0d0;line-height:1;margin-bottom:8px;user-select:none}
        h1{font-size:1.45rem;font-weight:600;margin-bottom:12px;color:#222}
        .desc{font-size:1rem;color:#555;line-height:1.65;margin-bottom:36px}
        details{background:#fff;border:1px solid #ddd;border-radius:5px;overflow:hidden}
        summary{padding:14px 18px;font-weight:600;font-size:.875rem;cursor:pointer;color:#555;list-style:none;display:flex;align-items:center;gap:8px;user-select:none}
        summary::-webkit-details-marker{display:none}
        summary::before{content:"›";font-size:1.1em;transition:transform .15s;display:inline-block}
        details[open] summary::before{transform:rotate(90deg)}
        summary:hover{color:#000;background:#fafafa}
        .detail-body{padding:16px 18px 18px;border-top:1px solid #eee}
        .lbl{font-size:.7rem;text-transform:uppercase;letter-spacing:.6px;color:#999;margin-bottom:5px;margin-top:14px}
        .lbl:first-child{margin-top:0}
        .exc-cls{font-family:ui-monospace,"Cascadia Code",SFMono-Regular,Menlo,monospace;font-size:.82rem;color:#c7254e;background:#f9f2f4;padding:3px 7px;border-radius:3px;display:inline-block}
        .exc-msg{font-family:ui-monospace,"Cascadia Code",SFMono-Regular,Menlo,monospace;font-size:.8rem;background:#f8f8f8;border:1px solid #e8e8e8;border-radius:3px;padding:10px 12px;white-space:pre-wrap;word-break:break-all;color:#444;margin-top:5px}
        .hint{margin-top:16px;padding-top:16px;border-top:1px solid #eee}
        .hint p{font-size:.875rem;line-height:1.65;margin-bottom:8px;color:#444}
        .hint code{font-family:ui-monospace,"Cascadia Code",SFMono-Regular,Menlo,monospace;font-size:.82em;background:#f0f0f0;padding:1px 5px;border-radius:2px}
        pre.cmd{background:#1e1e2e;color:#cdd6f4;padding:12px 16px;border-radius:4px;font-family:ui-monospace,"Cascadia Code",SFMono-Regular,Menlo,monospace;font-size:.82rem;overflow-x:auto;margin:8px 0 10px;line-height:1.5}
    </style>
</head>
<body>
    <header>
        <span class="logo">prime</span>
    </header>
    <main>
        <div class="status">503</div>
        <h1>The application is temporarily unavailable.</h1>
        <p class="desc">
            A system-level error prevented the application from starting.
            Please try again shortly. If the problem persists, contact your system administrator.
        </p>
        <details>
            <summary>Administrator details</summary>
            <div class="detail-body">
                <div class="lbl">Exception</div>
                <span class="exc-cls"><?= $displayClass ?></span>
                <div class="lbl">Message</div>
                <div class="exc-msg"><?= $displayMsg ?></div>
                <?php if ($isCachePerm): ?>
                <div class="hint">
                    <p>The web process (<code><?= $u ?>:<?= $g ?></code>) cannot write to the
                    application cache directory. Run the following commands on the server as
                    <code>root</code> from the project root:</p>
                    <pre class="cmd">chown -R <?= $u ?>:<?= $g ?> var/cache/
chmod -R 775 var/cache/</pre>
                    <p>If this recurs after every deployment, ensure your deploy script runs
                    <code>cache:clear</code> as the web user, or fixes ownership afterward.</p>
                </div>
                <?php endif ?>
            </div>
        </details>
    </main>
</body>
</html>
    <?php
    if ($exit) {
        exit;
    }
}
