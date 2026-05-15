<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\TokenParser;



use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\TwigBundle\Node\RenderNode;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Bundle\TwigBundle\TokenParser\RenderTokenParser;
use Twig\Compiler;
use Twig\Environment;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Parser;
use Twig\Source;

#[Group('legacy')]
/**
 */
class LegacyRenderTokenParserTest extends TestCase
{
    #[DataProvider('getTestsForRender')]    public function testCompile($source, $expected)
    {
        $env = new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock(), array('cache' => false, 'autoescape' => false, 'optimizations' => 0));
        $env->addTokenParser(new RenderTokenParser());
        $stream = $env->tokenize(new Source($source, ''));
        $parser = new Parser($env);
        $node = $parser->parse($stream)->getNode('body')->getNode(0);

        $this->assertSame(
            (new Compiler($env))->compile($expected)->getSource(),
            (new Compiler($env))->compile($node)->getSource()
        );
    }

    public static function getTestsForRender()
    {
        return array(
            array(
                '{% render "foo" %}',
                new RenderNode(
                    new ConstantExpression('foo', 1),
                    new ArrayExpression(array(), 1),
                    1,
                    'render'
                ),
            ),
            array(
                '{% render "foo", {foo: 1} %}',
                new RenderNode(
                    new ConstantExpression('foo', 1),
                    new ArrayExpression(array(
                        new ConstantExpression('foo', 1),
                        new ConstantExpression(1, 1),
                    ), 1),
                    1,
                    'render'
                ),
            ),
        );
    }
}
