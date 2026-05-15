<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\UnusedTagsPass;

class UnusedTagsPassTest extends TestCase
{
    public function testProcess()
    {
        $pass = new UnusedTagsPass();

        $formatter = $this->getMockBuilder('Symfony\Component\DependencyInjection\Compiler\LoggingFormatter')->getMock();
        $formatter
            ->expects($this->any())
            ->method('format')
            ->with($pass, 'Tag "kenrel.event_subscriber" was defined on service(s) "foo", "bar", but was never used. Did you mean "kernel.event_subscriber"?')
        ;

        $compiler = $this->getMockBuilder('Symfony\Component\DependencyInjection\Compiler\Compiler')->getMock();
        $compiler->expects($this->once())->method('getLoggingFormatter')->willReturn($formatter);

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->onlyMethods(array('findTaggedServiceIds', 'getCompiler', 'findUnusedTags', 'findTags'))->getMock();
        $container->expects($this->once())->method('getCompiler')->willReturn($compiler);
        $container->expects($this->once())
            ->method('findTags')
->willReturn(array('kenrel.event_subscriber'));
        $container->expects($this->once())
            ->method('findUnusedTags')
->willReturn(array('kenrel.event_subscriber', 'form.type'));
        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('kenrel.event_subscriber')
            ->willReturn(array(
                'foo' => array(),
                'bar' => array(),
));

        $pass->process($container);
    }
}
