<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\NumberFormatter;


use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Intl\Globals\IntlGlobals;
use Symfony\Component\Intl\NumberFormatter\NumberFormatter;

/**
 * Note that there are some values written like -2147483647 - 1. This is the lower 32bit int max and is a known
 * behavior of PHP.
 */
class NumberFormatterTest extends AbstractNumberFormatterTest
{
    /**
     */
    public function testConstructorWithUnsupportedLocale()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);

        new NumberFormatter('pt_BR');
    }

    /**
     */
    public function testConstructorWithUnsupportedStyle()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);

        new NumberFormatter('en', NumberFormatter::PATTERN_DECIMAL);
    }

    /**
     */
    public function testConstructorWithPatternDifferentThanNull()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentNotImplementedException::class);

        new NumberFormatter('en', NumberFormatter::DECIMAL, '');
    }

    /**
     */
    public function testSetAttributeWithUnsupportedAttribute()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);

        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::LENIENT_PARSE, null);
    }

    /**
     */
    public function testSetAttributeInvalidRoundingMode()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);

        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::ROUNDING_MODE, null);
    }

    public function testConstructWithoutLocale()
    {
        $this->assertInstanceOf(
            '\Symfony\Component\Intl\NumberFormatter\NumberFormatter',
            $this->getNumberFormatter(null, NumberFormatter::DECIMAL)
        );
    }

    public function testCreate()
    {
        $this->assertInstanceOf(
            '\Symfony\Component\Intl\NumberFormatter\NumberFormatter',
            NumberFormatter::create('en', NumberFormatter::DECIMAL)
        );
    }

    /**
     */
    public function testFormatWithCurrencyStyle()
    {
        $this->expectException(\RuntimeException::class);

        parent::testFormatWithCurrencyStyle();
    }

    #[DataProvider('formatTypeInt32Provider')]    public function testFormatTypeInt32($formatter, $value, $expected, $message = '')
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);

        parent::testFormatTypeInt32($formatter, $value, $expected, $message);
    }

    #[DataProvider('formatTypeInt32WithCurrencyStyleProvider')]    public function testFormatTypeInt32WithCurrencyStyle($formatter, $value, $expected, $message = '')
    {
        $this->expectException(\Symfony\Component\Intl\Exception\NotImplementedException::class);

        parent::testFormatTypeInt32WithCurrencyStyle($formatter, $value, $expected, $message);
    }

    #[DataProvider('formatTypeInt64Provider')]    public function testFormatTypeInt64($formatter, $value, $expected)
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);

        parent::testFormatTypeInt64($formatter, $value, $expected);
    }

    #[DataProvider('formatTypeInt64WithCurrencyStyleProvider')]    public function testFormatTypeInt64WithCurrencyStyle($formatter, $value, $expected)
    {
        $this->expectException(\Symfony\Component\Intl\Exception\NotImplementedException::class);

        parent::testFormatTypeInt64WithCurrencyStyle($formatter, $value, $expected);
    }

    #[DataProvider('formatTypeDoubleProvider')]    public function testFormatTypeDouble($formatter, $value, $expected)
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);

        parent::testFormatTypeDouble($formatter, $value, $expected);
    }

    #[DataProvider('formatTypeDoubleWithCurrencyStyleProvider')]    public function testFormatTypeDoubleWithCurrencyStyle($formatter, $value, $expected)
    {
        $this->expectException(\Symfony\Component\Intl\Exception\NotImplementedException::class);

        parent::testFormatTypeDoubleWithCurrencyStyle($formatter, $value, $expected);
    }

    /**
     */
    public function testGetPattern()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->getPattern();
    }

    public function testGetErrorCode()
    {
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $this->assertEquals(IntlGlobals::U_ZERO_ERROR, $formatter->getErrorCode());
    }

    /**
     */
    public function testParseCurrency()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->parseCurrency(null, $currency);
    }

    /**
     */
    public function testSetPattern()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setPattern(null);
    }

    /**
     */
    public function testSetSymbol()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setSymbol(null, null);
    }

    /**
     */
    public function testSetTextAttribute()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setTextAttribute(null, null);
    }

    protected static function getNumberFormatter($locale = 'en', $style = null, $pattern = null)
    {
        return new NumberFormatter($locale, $style, $pattern);
    }

    protected function getIntlErrorMessage()
    {
        return IntlGlobals::getErrorMessage();
    }

    protected function getIntlErrorCode()
    {
        return IntlGlobals::getErrorCode();
    }

    protected function isIntlFailure($errorCode)
    {
        return IntlGlobals::isFailure($errorCode);
    }
}
