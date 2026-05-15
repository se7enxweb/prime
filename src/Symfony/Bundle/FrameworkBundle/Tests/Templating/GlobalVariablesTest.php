<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating;


use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Templating\GlobalVariables;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;

class GlobalVariablesTest extends TestCase
{
    private $container;
    private $globals;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->globals = new GlobalVariables($this->container);
    }

    #[Group('legacy')]    public function testLegacyGetSecurity()
    {
        $securityContext = $this->getMockBuilder('Symfony\Component\Security\Core\SecurityContextInterface')->getMock();

        $this->assertNull($this->globals->getSecurity());
        $this->container->set('security.context', $securityContext);
        $this->assertSame($securityContext, $this->globals->getSecurity());
    }

    public function testGetUserNoTokenStorage()
    {
        $this->assertNull($this->globals->getUser());
    }

    public function testGetUserNoToken()
    {
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $this->container->set('security.token_storage', $tokenStorage);
        $this->assertNull($this->globals->getUser());
    }

    #[DataProvider('getUserProvider')]    public function testGetUser($user, $expectedUser)
    {
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();

        $this->container->set('security.token_storage', $tokenStorage);

        $token
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->willReturn($token);

$this->assertSame($expectedUser, $this->globals->getUser());
    }

    public static function getUserProvider()
    {
        $user = new \stdClass();
        $std = new \stdClass();
        $token = new \stdClass();

        return array(
            array($user, $user),
            array($std, $std),
            array($token, $token),
            array('Anon.', null),
            array(null, null),
            array(10, null),
            array(true, null),
        );
    }
}
