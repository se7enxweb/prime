<?php

/*
 * This file is part of the prime package.
 * (c) 2004-2026 7x <info@se7enx.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\PrimeSiteBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * PrimeSiteExtension — DependencyInjection extension for PrimeSiteBundle.
 *
 * Symfony2 bundles expose configuration to the container via an Extension
 * class. This extension registers no services — the default site bundle
 * is intentionally minimal (a status/health-check controller only).
 *
 * The alias 'prime_site' means that if any configuration were needed it
 * would appear under the prime_site: key in config.yml.
 *
 * @author 7x <info@se7enx.com>
 */
class PrimeSiteExtension extends Extension
{
    /**
     * Loads the bundle configuration into the container.
     *
     * No services are defined for this bundle; this method is a no-op
     * that satisfies the Extension interface contract.
     *
     * @param array            $configs   Merged configuration arrays
     * @param ContainerBuilder $container The DI container builder
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        // No services to register for the default site bundle.
    }

    /**
     * Returns the DI extension alias.
     *
     * Symfony2 derives the alias automatically from the class name by
     * stripping "Extension" and converting to snake_case. Declaring it
     * explicitly avoids ambiguity and documents the intent.
     *
     * @return string
     */
    public function getAlias()
    {
        return 'prime_site';
    }
}
