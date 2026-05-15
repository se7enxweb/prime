<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\EnumNode;

class EnumNodeTest extends TestCase
{
    public function testFinalizeValue()
    {
        $node = new EnumNode('foo', null, array('foo', 'bar'));
        $this->assertSame('foo', $node->finalize('foo'));
    }

    /**
     */
    public function testConstructionWithNoValues()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$values must contain at least one element.');

        new EnumNode('foo', null, array());
    }

    public function testConstructionWithOneValue()
    {
        $node = new EnumNode('foo', null, array('foo'));
        $this->assertSame('foo', $node->finalize('foo'));
    }

    public function testConstructionWithOneDistinctValue()
    {
        $node = new EnumNode('foo', null, array('foo', 'foo'));
        $this->assertSame('foo', $node->finalize('foo'));
    }

    /**
     */
    public function testFinalizeWithInvalidValue()
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value "foobar" is not allowed for path "foo". Permissible values: "foo", "bar"');

        $node = new EnumNode('foo', null, array('foo', 'bar'));
        $node->finalize('foobar');
    }
}
