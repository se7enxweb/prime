<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;



use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DependencyInjection\ContainerAwareHttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[Group('legacy')]
/**
 */
class ContainerAwareHttpKernelTest extends TestCase
{
    #[DataProvider('getProviderTypes')]    public function testHandle($type)
    {
        $request = new Request();
        $expected = new Response();
        $controller = function () use ($expected) {
            return $expected;
        };

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $calls = 0;
        $container
            ->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function ($id, $value, $scope) use (&$calls, $request) {
                ++$calls;

                TestCase::assertSame('request', $id);
                TestCase::assertSame('request', $scope);
                TestCase::assertSame(1 === $calls ? $request : null, $value);
            });
        $this
            ->expectsEnterScopeOnce($container)
            ->expectsLeaveScopeOnce($container)
        ;

        $dispatcher = new EventDispatcher();
        $resolver = $this->getResolverMockFor($controller, $request);
        $stack = new RequestStack();
        $kernel = new ContainerAwareHttpKernel($dispatcher, $container, $resolver, $stack);

        $actual = $kernel->handle($request, $type);

        $this->assertSame($expected, $actual, '->handle() returns the response');
    }

    #[DataProvider('getProviderTypes')]    public function testVerifyRequestStackPushPopDuringHandle($type)
    {
        $request = new Request();
        $expected = new Response();
        $controller = function () use ($expected) {
            return $expected;
        };

        $stack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')->onlyMethods(array('push', 'pop'))->getMock();
        $stack->expects($this->any())->method('push')->with($this->equalTo($request));
        $stack->expects($this->any())->method('pop');

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $dispatcher = new EventDispatcher();
        $resolver = $this->getResolverMockFor($controller, $request);
        $kernel = new ContainerAwareHttpKernel($dispatcher, $container, $resolver, $stack);

        $kernel->handle($request, $type);
    }

    #[DataProvider('getProviderTypes')]    public function testHandleRestoresThePreviousRequestOnException($type)
    {
        $request = new Request();
        $expected = new \Exception();
        $controller = function () use ($expected) {
            throw $expected;
        };

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $calls = 0;
        $container
            ->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function ($id, $value, $scope) use (&$calls, $request) {
                ++$calls;

                TestCase::assertSame('request', $id);
                TestCase::assertSame('request', $scope);
                TestCase::assertSame(1 === $calls ? $request : null, $value);
            });
        $this
            ->expectsEnterScopeOnce($container)
            ->expectsLeaveScopeOnce($container)
        ;

        $dispatcher = new EventDispatcher();
        $resolver = $this->getMockBuilder('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface')->getMock();
        $resolver = $this->getResolverMockFor($controller, $request);
        $stack = new RequestStack();
        $kernel = new ContainerAwareHttpKernel($dispatcher, $container, $resolver, $stack);

        try {
            $kernel->handle($request, $type);
            $this->fail('->handle() suppresses the controller exception');
        } catch (\PHPUnit\Framework\Exception $e) {
            throw $e;
        } catch (\PHPUnit\Framework\Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->assertSame($expected, $e, '->handle() throws the controller exception');
        }
    }

    public static function getProviderTypes()
    {
        return array(
            array(HttpKernelInterface::MASTER_REQUEST),
            array(HttpKernelInterface::SUB_REQUEST),
        );
    }

    private function getResolverMockFor($controller, $request)
    {
        $resolver = $this->getMockBuilder('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface')->getMock();
        $resolver->expects($this->once())
            ->method('getController')
            ->with($request)
            ->willReturn($controller);
        $resolver->expects($this->once())
            ->method('getArguments')
            ->with($request, $controller)
->willReturn(array());

        return $resolver;
    }

    private function expectsEnterScopeOnce($container)
    {
        $container
            ->expects($this->once())
            ->method('enterScope')
            ->with($this->equalTo('request'))
        ;

        return $this;
    }

    private function expectsLeaveScopeOnce($container)
    {
        $container
            ->expects($this->once())
            ->method('leaveScope')
            ->with($this->equalTo('request'))
        ;

        return $this;
    }
}
