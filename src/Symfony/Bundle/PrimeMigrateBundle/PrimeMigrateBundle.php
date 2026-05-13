<?php

/*
 * This file is part of the prime package.
 * (c) 2004-2026 7x <info@se7enx.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\PrimeMigrateBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * PrimeMigrateBundle — read-first, fix-optional migration helper commands.
 *
 * Provides the `prime:migrate:*` console command namespace to help developers
 * audit and partially automate the migration from Symfony 2.x on PHP 5.x/7.x
 * to 7x Prime on PHP 8.x.
 *
 * Commands are registered automatically from Command/ by the Symfony console
 * application via Bundle::registerCommands().
 *
 * All commands default to read-only (report) mode. Destructive writes only
 * happen when the caller explicitly passes --fix (and optionally removes
 * --dry-run). This ensures no accidental disk mutation.
 *
 * @author 7x <info@se7enx.com>
 */
class PrimeMigrateBundle extends Bundle
{
}
