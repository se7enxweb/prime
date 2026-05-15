<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;

class RegisterListenersPassTest extends TestCase
{
    /**
     * Tests that event subscribers not implementing EventSubscriberInterface
     * trigger an exception.
     *
     */
    public function testEventSubscriberWithoutInterface()
    {
        $this->expectException(\InvalidArgumentException::class);

        $builder = new ContainerBuilder();
        $builder->register('event_dispatcher');
        $builder->register('my_event_subscriber', 'stdClass')
            ->addTag('kernel.event_subscriber');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($builder);
    }

    public function testValidEventSubscriber()
    {
        $services = array(
            'my_event_subscriber' => array(0 => array()),
        );

        $builder = new ContainerBuilder();
        $eventDispatcherDefinition = $builder->register('event_dispatcher');
        $builder->register('my_event_subscriber', 'Symfony\Component\EventDispatcher\Tests\DependencyInjection\SubscriberService')
            ->addTag('kernel.event_subscriber');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($builder);

        $this->assertEquals(array(array('addSubscriberService', array('my_event_subscriber', 'Symfony\Component\EventDispatcher\Tests\DependencyInjection\SubscriberService'))), $eventDispatcherDefinition->getMethodCalls());
    }

    /**
     */
    public function testPrivateEventListener()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The service "foo" must be public as event listeners are lazy-loaded.');

        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->setPublic(false)->addTag('kernel.event_listener', array());
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    /**
     */
    public function testPrivateEventSubscriber()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The service "foo" must be public as event subscribers are lazy-loaded.');

        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->setPublic(false)->addTag('kernel.event_subscriber', array());
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    /**
     */
    public function testAbstractEventListener()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The service "foo" must not be abstract as event listeners are lazy-loaded.');

        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->setAbstract(true)->addTag('kernel.event_listener', array());
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    /**
     */
    public function testAbstractEventSubscriber()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The service "foo" must not be abstract as event subscribers are lazy-loaded.');

        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->setAbstract(true)->addTag('kernel.event_subscriber', array());
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }

    public function testEventSubscriberResolvableClassName()
    {
        $container = new ContainerBuilder();

        $container->setParameter('subscriber.class', 'Symfony\Component\EventDispatcher\Tests\DependencyInjection\SubscriberService');
        $container->register('foo', '%subscriber.class%')->addTag('kernel.event_subscriber', array());
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);

        $definition = $container->getDefinition('event_dispatcher');
        $expected_calls = array(
            array(
                'addSubscriberService',
                array(
                    'foo',
                    'Symfony\Component\EventDispatcher\Tests\DependencyInjection\SubscriberService',
                ),
            ),
        );
        $this->assertSame($expected_calls, $definition->getMethodCalls());
    }

    /**
     */
    public function testEventSubscriberUnresolvableClassName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You have requested a non-existent parameter "subscriber.class"');

        $container = new ContainerBuilder();
        $container->register('foo', '%subscriber.class%')->addTag('kernel.event_subscriber', array());
        $container->register('event_dispatcher', 'stdClass');

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->process($container);
    }
}

class SubscriberService implements \Symfony\Component\EventDispatcher\EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
    }
}
