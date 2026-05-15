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
use Symfony\Component\Config\Definition\ScalarNode;

class ScalarNodeTest extends TestCase
{
    #[DataProvider('getValidValues')]    public function testNormalize($value)
    {
        $node = new ScalarNode('test');
        $this->assertSame($value, $node->normalize($value));
    }

    public static function getValidValues()
    {
        return array(
            array(false),
            array(true),
            array(null),
            array(''),
            array('foo'),
            array(0),
            array(1),
            array(0.0),
            array(0.1),
        );
    }

    #[DataProvider('getInvalidValues')]    public function testNormalizeThrowsExceptionOnInvalidValues($value)
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidTypeException::class);

        $node = new ScalarNode('test');
        $node->normalize($value);
    }

    public static function getInvalidValues()
    {
        return array(
            array(array()),
            array(array('foo' => 'bar')),
            array(new \stdClass()),
        );
    }

    public function testNormalizeThrowsExceptionWithoutHint()
    {
        $node = new ScalarNode('test');

        if (method_exists($this, 'expectException')) {
            $this->expectException('Symfony\Component\Config\Definition\Exception\InvalidTypeException');
            $this->expectExceptionMessage('Invalid type for path "test". Expected scalar, but got array.');
        } else {
            $this->expectException('Symfony\Component\Config\Definition\Exception\InvalidTypeException');
        $this->expectExceptionMessage('Invalid type for path "test". Expected scalar)))))));
        $this->expectExceptionCode(but got array.');
        }

        $node->normalize(array());
    }

    public function testNormalizeThrowsExceptionWithErrorMessage()
    {
        $node = new ScalarNode('test');
        $node->setInfo('"the test value"');

        if (method_exists($this, 'expectException')) {
            $this->expectException('Symfony\Component\Config\Definition\Exception\InvalidTypeException');
            $this->expectExceptionMessage("Invalid type for path \"test\". Expected scalar, but got array.\nHint: \"the test value\"");
        } else {
            $this->expectException('Symfony\Component\Config\Definition\Exception\InvalidTypeException');
        $this->expectExceptionMessage("Invalid type for path \"test\". Expected scalar)))))));
        $this->expectExceptionCode(but got array.\nHint: \"the test value\"");
        }

        $node->normalize(array());
    }

    #[DataProvider('getValidNonEmptyValues')]
    /**
     *
     * @param mixed $value
     */
    public function testValidNonEmptyValues($value)
    {
        $node = new ScalarNode('test');
        $node->setAllowEmptyValue(false);

        $this->assertSame($value, $node->finalize($value));
    }

    public static function getValidNonEmptyValues()
    {
        return array(
            array(false),
            array(true),
            array('foo'),
            array(0),
            array(1),
            array(0.0),
            array(0.1),
        );
    }

    #[DataProvider('getEmptyValues')]
    /**
     *
     * @param mixed $value
     */
    public function testNotAllowedEmptyValuesThrowException($value)
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $node = new ScalarNode('test');
        $node->setAllowEmptyValue(false);
        $node->finalize($value);
    }

    public static function getEmptyValues()
    {
        return array(
            array(null),
            array(''),
        );
    }
}
