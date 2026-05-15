<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Cache;


use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Mapping\Cache\ApcCache;

#[Group('legacy')]
#[RequiresPhpExtension('apc')]
/**
 */
class LegacyApcCacheTest extends TestCase
{
    protected function setUp(): void
    {
        if (!filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN) || !filter_var(ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('APC is not enabled.');
        }
    }

    public function testWrite()
    {
        $meta = $this->getMockBuilder('Symfony\\Component\\Validator\\Mapping\\ClassMetadata')
            ->disableOriginalConstructor()
            ->onlyMethods(array('getClassName'))
            ->getMock();

        $meta->expects($this->once())
            ->method('getClassName')
            ->willReturn('bar');

        $cache = new ApcCache('foo');
        $cache->write($meta);

        $this->assertInstanceOf('Symfony\\Component\\Validator\\Mapping\\ClassMetadata', apc_fetch('foobar'), '->write() stores metadata in APC');
    }

    public function testHas()
    {
        $meta = $this->getMockBuilder('Symfony\\Component\\Validator\\Mapping\\ClassMetadata')
            ->disableOriginalConstructor()
            ->onlyMethods(array('getClassName'))
            ->getMock();

        $meta->expects($this->once())
            ->method('getClassName')
            ->willReturn('bar');

        apc_delete('foobar');

        $cache = new ApcCache('foo');
        $this->assertFalse($cache->has('bar'), '->has() returns false when there is no entry');

        $cache->write($meta);
        $this->assertTrue($cache->has('bar'), '->has() returns true when the is an entry');
    }

    public function testRead()
    {
        $meta = $this->getMockBuilder('Symfony\\Component\\Validator\\Mapping\\ClassMetadata')
            ->disableOriginalConstructor()
            ->onlyMethods(array('getClassName'))
            ->getMock();

        $meta->expects($this->once())
            ->method('getClassName')
            ->willReturn('bar');

        $cache = new ApcCache('foo');
        $cache->write($meta);

        $this->assertInstanceOf('Symfony\\Component\\Validator\\Mapping\\ClassMetadata', $cache->read('bar'), '->read() returns metadata');
    }
}
