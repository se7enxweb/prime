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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CredentialsExpiredException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\Role\SwitchUserRole;

class UserAuthenticationProviderTest extends TestCase
{
    public function testSupports()
    {
        $provider = $this->getProvider();

        $this->assertTrue($provider->supports($this->getSupportedToken()));
        $this->assertFalse($provider->supports($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock()));
    }

    /**
     */
    public function testAuthenticateWhenTokenIsNotSupported()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AuthenticationException::class);
        $this->expectExceptionMessage('The token is not supported by this authentication provider.');

        $provider = $this->getProvider();

        $provider->authenticate($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());
    }

    /**
     */
    public function testAuthenticateWhenUsernameIsNotFound()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\UsernameNotFoundException::class);

        $provider = $this->getProvider(false, false);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willThrowException(new UsernameNotFoundException())

        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     */
    public function testAuthenticateWhenUsernameIsNotFoundAndHideIsTrue()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\BadCredentialsException::class);

        $provider = $this->getProvider(false, true);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willThrowException(new UsernameNotFoundException())

        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     */
    public function testAuthenticateWhenProviderDoesNotReturnAnUserInterface()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AuthenticationServiceException::class);

        $provider = $this->getProvider(false, true);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn(null)

        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     */
    public function testAuthenticateWhenPreChecksFails()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\CredentialsExpiredException::class);

        $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        $userChecker->expects($this->once())
                    ->method('checkPreAuth')
                    ->willThrowException(new CredentialsExpiredException())

        ;

        $provider = $this->getProvider($userChecker);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock())

        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     */
    public function testAuthenticateWhenPostChecksFails()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccountExpiredException::class);

        $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        $userChecker->expects($this->once())
                    ->method('checkPostAuth')
                    ->willThrowException(new AccountExpiredException())

        ;

        $provider = $this->getProvider($userChecker);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock())

        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     */
    public function testAuthenticateWhenPostCheckAuthenticationFails()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\BadCredentialsException::class);
        $this->expectExceptionMessage('Bad credentials');

        $provider = $this->getProvider();
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock())

        ;
        $provider->expects($this->once())
                 ->method('checkAuthentication')
                 ->willThrowException(new BadCredentialsException())

        ;

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     */
    public function testAuthenticateWhenPostCheckAuthenticationFailsWithHideFalse()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\BadCredentialsException::class);
        $this->expectExceptionMessage('Foo');

        $provider = $this->getProvider(false, false);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock())

        ;
        $provider->expects($this->once())
                 ->method('checkAuthentication')
                 ->willThrowException(new BadCredentialsException('Foo'))

        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticate()
    {
        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $user->expects($this->once())
             ->method('getRoles')
             ->willReturn(array('ROLE_FOO'))

        ;

        $provider = $this->getProvider();
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($user)

        ;

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getCredentials')
              ->willReturn('foo')

        ;

        $token->expects($this->once())
              ->method('getRoles')
              ->willReturn(array())

        ;

        $authToken = $provider->authenticate($token);

        $this->assertInstanceOf('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken', $authToken);
        $this->assertSame($user, $authToken->getUser());
        $this->assertEquals(array(new Role('ROLE_FOO')), $authToken->getRoles());
        $this->assertEquals('foo', $authToken->getCredentials());
        $this->assertEquals(array('foo' => 'bar'), $authToken->getAttributes(), '->authenticate() copies token attributes');
    }

    public function testAuthenticateWithPreservingRoleSwitchUserRole()
    {
        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $user->expects($this->once())
             ->method('getRoles')
             ->willReturn(array('ROLE_FOO'))

        ;

        $provider = $this->getProvider();
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($user)

        ;

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getCredentials')
              ->willReturn('foo')

        ;

        $switchUserRole = new SwitchUserRole('foo', $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());
        $token->expects($this->once())
              ->method('getRoles')
              ->willReturn(array($switchUserRole))

        ;

        $authToken = $provider->authenticate($token);

        $this->assertInstanceOf('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken', $authToken);
        $this->assertSame($user, $authToken->getUser());
        
        // Check that ROLE_FOO is present in roles
        $roles = $authToken->getRoles();
        $hasRoleFoo = false;
        foreach ($roles as $role) {
            if ($role instanceof Role && $role->getRole() === 'ROLE_FOO') {
                $hasRoleFoo = true;
                break;
            }
        }
        $this->assertTrue($hasRoleFoo, 'ROLE_FOO should be in the authenticated token roles');
        $this->assertContains($switchUserRole, $roles, '', false, false);
        
        $this->assertEquals('foo', $authToken->getCredentials());
        $this->assertEquals(array('foo' => 'bar'), $authToken->getAttributes(), '->authenticate() copies token attributes');
    }

    protected function getSupportedToken()
    {
        $mock = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken')->onlyMethods(array('getCredentials', 'getProviderKey', 'getRoles'))->disableOriginalConstructor()->getMock();
        $mock
            ->expects($this->any())
            ->method('getProviderKey')
            ->willReturn('key')

        ;

        $mock->setAttributes(array('foo' => 'bar'));

        return $mock;
    }

    protected function getProvider($userChecker = false, $hide = true)
    {
        if (false === $userChecker) {
            $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        }

        return $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Provider\UserAuthenticationProvider')
            ->setConstructorArgs(array($userChecker, 'key', $hide))
            ->onlyMethods(array('retrieveUser', 'checkAuthentication'))
            ->getMock();
    }
}
