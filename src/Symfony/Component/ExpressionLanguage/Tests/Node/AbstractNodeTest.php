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


use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\Compiler;

abstract class AbstractNodeTest extends TestCase
{
    #[DataProvider('getEvaluateData')]    public function testEvaluate($expected, $node, $variables = array(), $functions = array())
    {
        $this->assertSame($expected, $node->evaluate($functions, $variables));
    }

    abstract public static function getEvaluateData();

    #[DataProvider('getCompileData')]    public function testCompile($expected, $node, $functions = array())
    {
        $compiler = new Compiler($functions);
        $node->compile($compiler);
        $this->assertSame($expected, $compiler->getSource());
    }

    abstract public static function getCompileData();
}
