<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Tests\Fixtures\TestProvider;

class ExpressionLanguageTest extends TestCase
{
    public function testCachedParse()
    {
        $cacheMock = $this->getMockBuilder('Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface')->getMock();
        $savedParsedExpression = null;
        $expressionLanguage = new ExpressionLanguage($cacheMock);

        $cacheMock
            ->expects($this->exactly(2))
            ->method('fetch')
            ->with('1 + 1//')
            ->willReturnCallback(function () use (&$savedParsedExpression) {
                return $savedParsedExpression;
            })

        ;
        $cacheMock
            ->expects($this->exactly(1))
            ->method('save')
            ->with('1 + 1//', $this->isInstanceOf('Symfony\Component\ExpressionLanguage\ParsedExpression'))
            ->willReturnCallback(function ($key, $expression) use (&$savedParsedExpression) {
                $savedParsedExpression = $expression;
            })

        ;

$parsedExpression = $expressionLanguage->parse('1 + 1', array());
        $this->assertSame($savedParsedExpression, $parsedExpression);

$parsedExpression = $expressionLanguage->parse('1 + 1', array());
        $this->assertSame($savedParsedExpression, $parsedExpression);
    }

    public function testConstantFunction()
    {
        $expressionLanguage = new ExpressionLanguage();
        $this->assertEquals(PHP_VERSION, $expressionLanguage->evaluate('constant("PHP_VERSION")'));

        $expressionLanguage = new ExpressionLanguage();
        $this->assertEquals('constant("PHP_VERSION")', $expressionLanguage->compile('constant("PHP_VERSION")'));
    }

    public function testProviders()
    {
        $expressionLanguage = new ExpressionLanguage(null, array(new TestProvider()));
        $this->assertEquals('foo', $expressionLanguage->evaluate('identity("foo")'));
        $this->assertEquals('"foo"', $expressionLanguage->compile('identity("foo")'));
    }

    #[DataProvider('shortCircuitProviderEvaluate')]
    public function testShortCircuitOperatorsEvaluate($expression, array $values, $expected)
    {
        $expressionLanguage = new ExpressionLanguage();
        $this->assertEquals($expected, $expressionLanguage->evaluate($expression, $values));
    }

    #[DataProvider('shortCircuitProviderCompile')]
    public function testShortCircuitOperatorsCompile($expression, array $names, $expected)
    {
        $result = null;
        $expressionLanguage = new ExpressionLanguage();
        eval(sprintf('$result = %s;', $expressionLanguage->compile($expression, $names)));
        $this->assertSame($expected, $result);
    }

    public function testParseThrowsInsteadOfNotice()
    {
        $this->expectException(\Symfony\Component\ExpressionLanguage\SyntaxError::class);
        $this->expectExceptionMessage('Unexpected end of expression around position 6 for expression `node.`.');

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->parse('node.', array('node'));
    }

    public static function shortCircuitProviderEvaluate()
    {
        // Use a simple stdClass - due to short-circuit evaluation, foo() will never be called
        $object = new \stdClass();

        return array(
            array('false and object.foo()', array('object' => $object), false),
            array('false && object.foo()', array('object' => $object), false),
            array('true || object.foo()', array('object' => $object), true),
            array('true or object.foo()', array('object' => $object), true),
        );
    }

    public static function shortCircuitProviderCompile()
    {
        return array(
            array('false and foo', array('foo' => 'foo'), false),
            array('false && foo', array('foo' => 'foo'), false),
            array('true || foo', array('foo' => 'foo'), true),
            array('true or foo', array('foo' => 'foo'), true),
        );
    }

    public function testCachingForOverriddenVariableNames()
    {
        $expressionLanguage = new ExpressionLanguage();
        $expression = 'a + b';
        $expressionLanguage->evaluate($expression, array('a' => 1, 'b' => 1));
        $result = $expressionLanguage->compile($expression, array('a', 'B' => 'b'));
        $this->assertSame('($a + $B)', $result);
    }

    public function testStrictEquality()
    {
        $expressionLanguage = new ExpressionLanguage();
        $expression = '123 === a';
        $result = $expressionLanguage->compile($expression, array('a'));
        $this->assertSame('(123 === $a)', $result);
    }

    public function testCachingWithDifferentNamesOrder()
    {
        $cacheMock = $this->getMockBuilder('Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface')->getMock();
        $expressionLanguage = new ExpressionLanguage($cacheMock);
        $savedParsedExpressions = array();
        $cacheMock
            ->expects($this->exactly(2))
            ->method('fetch')
            ->willReturnCallback(function ($key) use (&$savedParsedExpressions) {
                return isset($savedParsedExpressions[$key]) ? $savedParsedExpressions[$key] : null;
            })

        ;
        $cacheMock
            ->expects($this->exactly(1))
            ->method('save')
            ->willReturnCallback(function ($key, $expression) use (&$savedParsedExpressions) {
                $savedParsedExpressions[$key] = $expression;
            })

        ;

        $expression = 'a + b';
$expressionLanguage->compile($expression, array('a', 'B' => 'b'));
$expressionLanguage->compile($expression, array('B' => 'b', 'a'));
    }

    #[DataProvider('getRegisterCallbacks')]
    public function testRegisterAfterParse($registerCallback)
    {
        $this->expectException(\LogicException::class);

        $el = new ExpressionLanguage();
        $el->parse('1 + 1', array());
        $registerCallback($el);
    }

    #[DataProvider('getRegisterCallbacks')]
    public function testRegisterAfterEval($registerCallback)
    {
        $this->expectException(\LogicException::class);

        $el = new ExpressionLanguage();
        $el->evaluate('1 + 1');
        $registerCallback($el);
    }

    /**
     */
    public function testCallBadCallable()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unable to call method \"\\w+\" of object \"\\w+\"./');

        $el = new ExpressionLanguage();
        $el->evaluate('foo.myfunction()', array('foo' => new \stdClass()));
    }

    #[DataProvider('getRegisterCallbacks')]
    public function testRegisterAfterCompile($registerCallback)
    {
        $this->expectException(\LogicException::class);

        $el = new ExpressionLanguage();
        $el->compile('1 + 1');
        $registerCallback($el);
    }

    public static function getRegisterCallbacks()
    {
        return array(
            array(
                function (ExpressionLanguage $el) {
                    $el->register('fn', function () {}, function () {});
                },
            ),
            array(
                function (ExpressionLanguage $el) {
                    $el->addFunction(new ExpressionFunction('fn', function () {}, function () {}));
                },
            ),
            array(
                function (ExpressionLanguage $el) {
                    $el->registerProvider(new TestProvider());
                },
            ),
        );
    }
}
