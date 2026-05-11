<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\UserProvider\DummyProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SecurityExtensionTest extends TestCase
{
    /**
     */
    public function testInvalidCheckPath()
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->expectExceptionMessage('The check_path \"/some_area/login_check\" for login method \"form_login\" is not matched by the firewall pattern \"/secured_area/.*\".');

        $container = $this->getRawContainer();

        $container->loadFromExtension('security', array(
            'providers' => array(
                'default' => array('id' => 'foo'),
            ),

            'firewalls' => array(
                'some_firewall' => array(
                    'pattern' => '/secured_area/.*',
                    'form_login' => array(
                        'check_path' => '/some_area/login_check',
                    ),
                ),
            ),
        ));

        $container->compile();
    }

    /**
     */
    public function testFirewallWithoutAuthenticationListener()
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->expectExceptionMessage('No authentication listener registered for firewall \"some_firewall\"');

        $container = $this->getRawContainer();

        $container->loadFromExtension('security', array(
            'providers' => array(
                'default' => array('id' => 'foo'),
            ),

            'firewalls' => array(
                'some_firewall' => array(
                    'pattern' => '/.*',
                ),
            ),
        ));

        $container->compile();
    }

    /**
     */
    public function testFirewallWithInvalidUserProvider()
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unable to create definition for \"security.user.provider.concrete.my_foo\" user provider');

        $container = $this->getRawContainer();

        $extension = $container->getExtension('security');
        $extension->addUserProviderFactory(new DummyProvider());

        $container->loadFromExtension('security', array(
            'providers' => array(
                'my_foo' => array('foo' => array()),
            ),

            'firewalls' => array(
                'some_firewall' => array(
                    'pattern' => '/.*',
                    'http_basic' => array(),
                ),
            ),
        ));

        $container->compile();
    }

    public function testDisableRoleHierarchyVoter()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', array(
            'providers' => array(
                'default' => array('id' => 'foo'),
            ),

            'role_hierarchy' => null,

            'firewalls' => array(
                'some_firewall' => array(
                    'pattern' => '/.*',
                    'http_basic' => null,
                ),
            ),
        ));

        $container->compile();

        $this->assertFalse($container->hasDefinition('security.access.role_hierarchy_voter'));
    }

    public function testGuardHandlerIsPassedStatelessFirewalls()
    {
        $container = $this->getRawContainer();

        $container->loadFromExtension('security', array(
            'providers' => array(
                'default' => array('id' => 'foo'),
            ),

            'firewalls' => array(
                'some_firewall' => array(
                    'pattern' => '^/admin',
                    'http_basic' => null,
                ),
                'stateless_firewall' => array(
                    'pattern' => '/.*',
                    'stateless' => true,
                    'http_basic' => null,
                ),
            ),
        ));

        $container->compile();
        $definition = $container->getDefinition('security.authentication.guard_handler');
        $this->assertSame(array('stateless_firewall'), $definition->getArgument(2));
    }

    protected function getRawContainer()
    {
        $container = new ContainerBuilder();
        $security = new SecurityExtension();
        $container->registerExtension($security);

        $bundle = new SecurityBundle();
        $bundle->build($container);

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());

        return $container;
    }

    protected function getContainer()
    {
        $container = $this->getRawContainer();
        $container->compile();

        return $container;
    }
}
