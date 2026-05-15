<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests\Iterator;


use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Finder\Comparator\DateComparator;
use Symfony\Component\Finder\Iterator\DateRangeFilterIterator;

class DateRangeFilterIteratorTest extends RealIteratorTestCase
{
    #[DataProvider('getAcceptData')]    public function testAccept($size, $expected)
    {
        $files = self::$files;
        $files[] = self::toAbsolute('doesnotexist');
        $inner = new Iterator($files);

        $iterator = new DateRangeFilterIterator($inner, $size);

        $this->assertIterator($expected, $iterator);
    }

    public static function getAcceptData()
    {
        $since20YearsAgo = array(
            '.git',
            'test.py',
            'foo',
            'toto',
            'toto/.git',
            '.bar',
            '.foo',
            '.foo/.bar',
            'foo bar',
            '.foo/bar',
        );

        $since2MonthsAgo = array(
            '.git',
            'test.py',
            'foo',
            'toto',
            'toto/.git',
            '.bar',
            '.foo',
            '.foo/.bar',
            'foo bar',
            '.foo/bar',
        );

        $untilLastMonth = array(
            'foo/bar.tmp',
            'test.php',
        );

        return array(
            array(array(new DateComparator('since 20 years ago')), self::toAbsolute($since20YearsAgo)),
            array(array(new DateComparator('since 2 months ago')), self::toAbsolute($since2MonthsAgo)),
            array(array(new DateComparator('until last month')), self::toAbsolute($untilLastMonth)),
        );
    }
}
