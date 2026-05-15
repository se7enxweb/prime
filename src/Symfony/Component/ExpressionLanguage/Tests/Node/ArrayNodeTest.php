<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Tests\Node;

use Symfony\Component\ExpressionLanguage\Node\ArrayNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;

class ArrayNodeTest extends AbstractNodeTest
{
    public function testSerialization()
    {
        $node = $this->createArrayNode();
        $node->addElement(new ConstantNode('foo'));

        $serializedNode = serialize($node);
        $unserializedNode = unserialize($serializedNode);

        $this->assertEquals($node, $unserializedNode);
        $this->assertNotEquals($this->createArrayNode(), $unserializedNode);
    }

    public static function getEvaluateData()
    {
        return array(
            array(array('b' => 'a', 'b'), self::getArrayNode()),
        );
    }

    public static function getCompileData()
    {
        return array(
            array('array("b" => "a", 0 => "b")', self::getArrayNode()),
        );
    }

    protected static function getArrayNode()
    {
        $array = static::createArrayNode();
        $array->addElement(new ConstantNode('a'), new ConstantNode('b'));
        $array->addElement(new ConstantNode('b'));

        return $array;
    }

    protected static function createArrayNode()
    {
        return new ArrayNode();
    }
}
