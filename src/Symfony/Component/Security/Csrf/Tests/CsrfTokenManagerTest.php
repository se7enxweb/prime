<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\Tests;


use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CsrfTokenManagerTest extends TestCase
{
    #[DataProvider('getManagerGeneratorAndStorage')]    public function testGetNonExistingToken($namespace, $manager, $storage, $generator)
    {
        $storage->expects($this->once())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(false);

        $generator->expects($this->once())
            ->method('generateToken')
            ->willReturn('TOKEN');

        $storage->expects($this->once())
            ->method('setToken')
            ->with($namespace.'token_id', 'TOKEN');

        $token = $manager->getToken('token_id');

        $this->assertInstanceOf('Symfony\Component\Security\Csrf\CsrfToken', $token);
        $this->assertSame('token_id', $token->getId());
        $this->assertSame('TOKEN', $token->getValue());
    }

    #[DataProvider('getManagerGeneratorAndStorage')]    public function testUseExistingTokenIfAvailable($namespace, $manager, $storage)
    {
        $storage->expects($this->once())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(true);

        $storage->expects($this->once())
            ->method('getToken')
            ->with($namespace.'token_id')
            ->willReturn('TOKEN');

        $token = $manager->getToken('token_id');

        $this->assertInstanceOf('Symfony\Component\Security\Csrf\CsrfToken', $token);
        $this->assertSame('token_id', $token->getId());
        $this->assertSame('TOKEN', $token->getValue());
    }

    #[DataProvider('getManagerGeneratorAndStorage')]    public function testRefreshTokenAlwaysReturnsNewToken($namespace, $manager, $storage, $generator)
    {
        $storage->expects($this->never())
            ->method('hasToken');

        $generator->expects($this->once())
            ->method('generateToken')
            ->willReturn('TOKEN');

        $storage->expects($this->once())
            ->method('setToken')
            ->with($namespace.'token_id', 'TOKEN');

        $token = $manager->refreshToken('token_id');

        $this->assertInstanceOf('Symfony\Component\Security\Csrf\CsrfToken', $token);
        $this->assertSame('token_id', $token->getId());
        $this->assertSame('TOKEN', $token->getValue());
    }

    #[DataProvider('getManagerGeneratorAndStorage')]    public function testMatchingTokenIsValid($namespace, $manager, $storage)
    {
        $storage->expects($this->once())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(true);

        $storage->expects($this->once())
            ->method('getToken')
            ->with($namespace.'token_id')
            ->willReturn('TOKEN');

        $this->assertTrue($manager->isTokenValid(new CsrfToken('token_id', 'TOKEN')));
    }

    #[DataProvider('getManagerGeneratorAndStorage')]    public function testNonMatchingTokenIsNotValid($namespace, $manager, $storage)
    {
        $storage->expects($this->once())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(true);

        $storage->expects($this->once())
            ->method('getToken')
            ->with($namespace.'token_id')
            ->willReturn('TOKEN');

        $this->assertFalse($manager->isTokenValid(new CsrfToken('token_id', 'FOOBAR')));
    }

    #[DataProvider('getManagerGeneratorAndStorage')]    public function testNonExistingTokenIsNotValid($namespace, $manager, $storage)
    {
        $storage->expects($this->once())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(false);

        $storage->expects($this->never())
            ->method('getToken');

        $this->assertFalse($manager->isTokenValid(new CsrfToken('token_id', 'FOOBAR')));
    }

    #[DataProvider('getManagerGeneratorAndStorage')]    public function testRemoveToken($namespace, $manager, $storage)
    {
        $storage->expects($this->once())
            ->method('removeToken')
            ->with($namespace.'token_id')
            ->willReturn('REMOVED_TOKEN');

        $this->assertSame('REMOVED_TOKEN', $manager->removeToken('token_id'));
    }

    public function testNamespaced()
    {
        $generator = $this->getMockBuilder('Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface')->getMock();
        $storage = $this->getMockBuilder('Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface')->getMock();

        $requestStack = new RequestStack();
        $requestStack->push(new Request(array(), array(), array(), array(), array(), array('HTTPS' => 'on')));

        $manager = new CsrfTokenManager($generator, $storage, null, $requestStack);

        $token = $manager->getToken('foo');
        $this->assertSame('foo', $token->getId());
    }

    public static function getManagerGeneratorAndStorage()
    {
        $data = array();

        list($generator, $storage) = self::getGeneratorAndStorage();
        $data[] = array('', new CsrfTokenManager($generator, $storage, ''), $storage, $generator);

        list($generator, $storage) = self::getGeneratorAndStorage();
        $data[] = array('https-', new CsrfTokenManager($generator, $storage), $storage, $generator);

        list($generator, $storage) = self::getGeneratorAndStorage();
        $data[] = array('aNamespace-', new CsrfTokenManager($generator, $storage, 'aNamespace-'), $storage, $generator);

        $requestStack = new RequestStack();
        $requestStack->push(new Request(array(), array(), array(), array(), array(), array('HTTPS' => 'on')));
        list($generator, $storage) = self::getGeneratorAndStorage();
        $data[] = array('https-', new CsrfTokenManager($generator, $storage, $requestStack), $storage, $generator);

        list($generator, $storage) = self::getGeneratorAndStorage();
        $data[] = array('generated-', new CsrfTokenManager($generator, $storage, function () {
            return 'generated-';
        }), $storage, $generator);

        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        list($generator, $storage) = self::getGeneratorAndStorage();
        $data[] = array('', new CsrfTokenManager($generator, $storage, $requestStack), $storage, $generator);

        return $data;
    }

    private static function getGeneratorAndStorage()
    {
        $gen = new \PHPUnit\Framework\MockObject\Generator\Generator();
        return array(
            $gen->testDouble('Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface', true, true),
            $gen->testDouble('Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface', true, true),
        );
    }

    protected function setUp(): void
    {
        $_SERVER['HTTPS'] = 'on';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($_SERVER['HTTPS']);
    }
}
