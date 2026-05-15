<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\User;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Security\Core\User\LdapUserProvider;

#[RequiresPhpExtension('ldap')]
class LdapUserProviderTest extends TestCase
{
    /**
     */
    public function testLoadUserByUsernameFailsIfCantConnectToLdap()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\UsernameNotFoundException::class);

        $ldap = $this->getMockBuilder('Symfony\Component\Ldap\LdapClientInterface')->getMock();
        $ldap
            ->expects($this->once())
            ->method('bind')
            ->willThrowException(new ConnectionException())

        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $provider->loadUserByUsername('foo');
    }

    /**
     */
    public function testLoadUserByUsernameFailsIfNoLdapEntries()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\UsernameNotFoundException::class);

        $ldap = $this->getMockBuilder('Symfony\Component\Ldap\LdapClientInterface')->getMock();
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->willReturn('foo')

        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $provider->loadUserByUsername('foo');
    }

    /**
     */
    public function testLoadUserByUsernameFailsIfMoreThanOneLdapEntry()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\UsernameNotFoundException::class);

        $ldap = $this->getMockBuilder('Symfony\Component\Ldap\LdapClientInterface')->getMock();
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->willReturn('foo')

        ;
        $ldap
            ->expects($this->once())
            ->method('find')
            ->willReturn(array(
                array(),
                array(),
                'count' => 2,
            ))

        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $provider->loadUserByUsername('foo');
    }

    public function testSuccessfulLoadUserByUsername()
    {
        $ldap = $this->getMockBuilder('Symfony\Component\Ldap\LdapClientInterface')->getMock();
        $ldap
            ->expects($this->once())
            ->method('escape')
            ->willReturn('foo')

        ;
        $ldap
            ->expects($this->once())
            ->method('find')
            ->willReturn(array(
                array(
                    'sAMAccountName' => 'foo',
                    'userpassword' => 'bar',
                ),
                'count' => 1,
            ))

        ;

        $provider = new LdapUserProvider($ldap, 'ou=MyBusiness,dc=symfony,dc=com');
        $this->assertInstanceOf(
            'Symfony\Component\Security\Core\User\User',
            $provider->loadUserByUsername('foo')
        );
    }
}
