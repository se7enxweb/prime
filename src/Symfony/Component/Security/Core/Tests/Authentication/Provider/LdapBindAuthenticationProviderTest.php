<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\Provider;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Security\Core\Authentication\Provider\LdapBindAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\User;

#[RequiresPhpExtension('ldap')]
class LdapBindAuthenticationProviderTest extends TestCase
{
    /**
     */
    public function testEmptyPasswordShouldThrowAnException()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\BadCredentialsException::class);
        $this->expectExceptionMessage('The presented password must not be empty.');

        $userProvider = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserProviderInterface')->getMock();
        $ldap = $this->getMockBuilder('Symfony\Component\Ldap\LdapClientInterface')->getMock();
        $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');

        $reflection->invoke($provider, new User('foo', null), new UsernamePasswordToken('foo', '', 'key'));
    }

    /**
     */
    public function testNullPasswordShouldThrowAnException()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\BadCredentialsException::class);
        $this->expectExceptionMessage('The presented password must not be empty.');

        $userProvider = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserProviderInterface')->getMock();
        $ldap = $this->getMockBuilder('Symfony\Component\Ldap\LdapClientInterface')->getMock();
        $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');

        $reflection->invoke($provider, new User('foo', null), new UsernamePasswordToken('foo', null, 'key'));
    }

    /**
     */
    public function testBindFailureShouldThrowAnException()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\BadCredentialsException::class);
        $this->expectExceptionMessage('The presented password is invalid.');

        $userProvider = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserProviderInterface')->getMock();
        $ldap = $this->getMockBuilder('Symfony\Component\Ldap\LdapClientInterface')->getMock();
        $ldap
            ->expects($this->once())
            ->method('bind')
            ->willThrowException(new ConnectionException())

        ;
        $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'checkAuthentication');

        $reflection->invoke($provider, new User('foo', null), new UsernamePasswordToken('foo', 'bar', 'key'));
    }

    public function testRetrieveUser()
    {
        $userProvider = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserProviderInterface')->getMock();
        $userProvider
            ->expects($this->once())
            ->method('loadUserByUsername')
            ->with('foo')
        ;
        $ldap = $this->getMockBuilder('Symfony\Component\Ldap\LdapClientInterface')->getMock();

        $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();

        $provider = new LdapBindAuthenticationProvider($userProvider, $userChecker, 'key', $ldap);
        $reflection = new \ReflectionMethod($provider, 'retrieveUser');

        $reflection->invoke($provider, 'foo', new UsernamePasswordToken('foo', 'bar', 'key'));
    }
}
