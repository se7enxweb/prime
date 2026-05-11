<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;

class IniFileLoaderTest extends TestCase
{
    protected static $fixturesPath;

    protected $container;
    protected $loader;

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
    }

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->loader = new IniFileLoader($this->container, new FileLocator(self::$fixturesPath.'/ini'));
    }

    public function testIniFileCanBeLoaded()
    {
        $this->loader->load('parameters.ini');
        $this->assertEquals(array('foo' => 'bar', 'bar' => '%foo%'), $this->container->getParameterBag()->all(), '->load() takes a single file name as its first argument');
    }

    /**
     */
    public function testExceptionIsRaisedWhenIniFileDoesNotExist()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The file \"foo.ini\" does not exist (in:');

        $this->loader->load('foo.ini');
    }

    /**
     */
    public function testExceptionIsRaisedWhenIniFileCannotBeParsed()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The \"nonvalid.ini\" file is not valid.');

        @$this->loader->load('nonvalid.ini');
    }

    public function testSupports()
    {
        $loader = new IniFileLoader(new ContainerBuilder(), new FileLocator());

        $this->assertTrue($loader->supports('foo.ini'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');
    }
}
