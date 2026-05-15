<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\NumberFormatter\Verification;

use Symfony\Component\Intl\Tests\NumberFormatter\AbstractNumberFormatterTest;
use Symfony\Component\Intl\Util\IntlTestHelper;

/**
 * Note that there are some values written like -2147483647 - 1. This is the lower 32bit int max and is a known
 * behavior of PHP.
 */
class NumberFormatterTest extends AbstractNumberFormatterTest
{
    protected function setUp(): void
    {
        IntlTestHelper::requireFullIntl($this, '55.1');

        parent::setUp();
    }

    public function testCreate()
    {
        $this->assertInstanceOf('\NumberFormatter', \NumberFormatter::create('en', \NumberFormatter::DECIMAL));
    }

    public function testGetTextAttribute()
    {
        IntlTestHelper::requireFullIntl($this, '57.1');

        parent::testGetTextAttribute();
    }

    protected static function getNumberFormatter($locale = 'en', $style = null, $pattern = null)
    {
        return new \NumberFormatter($locale, $style, $pattern);
    }

    protected function getIntlErrorMessage()
    {
        return intl_get_error_message();
    }

    protected function getIntlErrorCode()
    {
        return intl_get_error_code();
    }

    protected function isIntlFailure($errorCode)
    {
        return intl_is_failure($errorCode);
    }

    public static function formatFractionDigitsProvider()
    {
        return array(
            array(1.123, '1.123', null, 0),
            array(1.123, '1', 0, 0),
            array(1.123, '1.1', 1, 1),
            array(1.123, '1.12', 2, 2),
            array(1.123, '1.123', -1, 0),
            array(1.123, '1.123', 'abc', 0),
        );
    }

    public static function formatGroupingUsedProvider()
    {
        return array(
            array(1000, '1,000', null, 1),
            array(1000, '1000', 0, 0),
            array(1000, '1,000', 1, 1),
            array(1000, '1,000', 2, 1),
            array(1000, '1,000', 'abc', 1),
            array(1000, '1,000', -1, 1),
        );
    }

    public static function parseProvider()
    {
        return array(
            array('prefix1', false, '->parse() does not parse a number with a string prefix.', 0),
            array('1.4suffix', (float) 1.4, '->parse() parses a number with a string suffix.', 3),
            array('-.4suffix', (float) -0.4, '->parse() parses a negative dot float with suffix.', 3),
            array('-123,4', false, '->parse() does not parse when invalid grouping used.', 1),
            array('-123,4567', false, '->parse() does not parse when invalid grouping used.', 1),
            array('-123,,456', -123.0, '->parse() does not parse when invalid grouping used.', 4),
            array('-123,,456', -123.0, '->parse() parses when grouping is disabled.', 4, false),
            array('239.', 239.0, '->parse() parses when string ends with decimal separator.', 4, false),
        );
    }
}
