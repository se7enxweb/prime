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


use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\FloatNode;

class FloatNodeTest extends TestCase
{
    #[DataProvider('getValidValues')]    public function testNormalize($value)
    {
        $node = new FloatNode('test');
        $this->assertSame($value, $node->normalize($value));
    }

    #[DataProvider('getValidValues')]
    /**
     *
     * @param int $value
     */
    public function testValidNonEmptyValues($value)
    {
        $node = new FloatNode('test');
        $node->setAllowEmptyValue(false);

        $this->assertSame($value, $node->finalize($value));
    }

    public static function getValidValues()
    {
        return array(
            array(1798.0),
            array(-678.987),
            array(12.56E45),
            array(0.0),
            // Integer are accepted too, they will be cast
            array(17),
            array(-10),
            array(0),
        );
    }

    #[DataProvider('getInvalidValues')]    public function testNormalizeThrowsExceptionOnInvalidValues($value)
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidTypeException::class);

        $node = new FloatNode('test');
        $node->normalize($value);
    }

    public static function getInvalidValues()
    {
        return array(
            array(null),
            array(''),
            array('foo'),
            array(true),
            array(false),
            array(array()),
            array(array('foo' => 'bar')),
            array(new \stdClass()),
        );
    }
}
