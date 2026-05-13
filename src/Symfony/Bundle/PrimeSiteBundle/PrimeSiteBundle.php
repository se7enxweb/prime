<?php

/*
 * This file is part of the prime package.
 * (c) 2004-2026 7x <info@se7enx.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\PrimeSiteBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * PrimeSiteBundle — default 'site' application bundle for prime.
 *
 * Symfony2 equivalent of the primer symfony1 'site' application.
 * Maps to apps/site/ in the symfony1 primer project structure:
 *
 *   primer (symfony1)                prime (Symfony2)
 *   ─────────────────────────────    ────────────────────────────────────────
 *   apps/site/                     → src/Symfony/Bundle/PrimeSiteBundle/
 *   apps/site/modules/status/      → Controller/StatusController.php
 *   apps/site/config/routing.php   → Resources/config/routing.yml
 *   apps/site/templates/layout.php → Resources/views/layout.html.twig
 *   modules/status/templates/      → Resources/views/Status/
 *
 * @author 7x <info@se7enx.com>
 */
class PrimeSiteBundle extends Bundle
{
}
