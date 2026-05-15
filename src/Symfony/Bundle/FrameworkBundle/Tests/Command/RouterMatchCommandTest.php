<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\RouterDebugCommand;
use Symfony\Bundle\FrameworkBundle\Command\RouterMatchCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouterMatchCommandTest extends TestCase
{
    public function testWithMatchPath()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array('path_info' => '/foo', 'foo'), array('decorated' => false));

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('Route Name   | foo', $tester->getDisplay());
    }

    public function testWithNotMatchPath()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array('path_info' => '/test', 'foo'), array('decorated' => false));

        $this->assertEquals(1, $ret, 'Returns 1 in case of failure');
        $this->assertStringContainsString('None of the routes match the path "/test"', $tester->getDisplay());
    }

    /**
     * @return CommandTester
     */
    private function createCommandTester()
    {
        $application = new Application();

        $command = new RouterMatchCommand();
        $command->setContainer($this->getContainer());
        $application->add($command);

        $command = new RouterDebugCommand();
        $command->setContainer($this->getContainer());
        $application->add($command);

        return new CommandTester($application->find('router:match'));
    }

    private function getContainer()
    {
        $routeCollection = new RouteCollection();
        $routeCollection->add('foo', new Route('foo'));
        $requestContext = new RequestContext();
        $router = $this->getMockBuilder('Symfony\Component\Routing\RouterInterface')->getMock();
        $router
            ->expects($this->any())
            ->method('getRouteCollection')
            ->willReturn($routeCollection)
        ;
        $router
            ->expects($this->any())
            ->method('getContext')
            ->willReturn($requestContext)
        ;

        $loader = $this->getMockBuilder('Symfony\Bundle\FrameworkBundle\Routing\DelegatingLoader')
            ->disableOriginalConstructor()
            ->getMock();

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container
            ->expects($this->once())
            ->method('has')
            ->with('router')
            ->willReturn(true);
        $container->method('get')
            ->willReturnMap(array(
                array('router', 1, $router),
                array('controller_name_converter', 1, $loader),
));

        return $container;
    }
}
