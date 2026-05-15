<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Data\Bundle\Reader;


use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReader;
use Symfony\Component\Intl\Exception\ResourceBundleNotFoundException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BundleEntryReaderTest extends TestCase
{
    const RES_DIR = '/res/dir';

    /**
     * @var BundleEntryReader
     */
    private $reader;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $readerImpl;

    private static $data = array(
        'Entries' => array(
            'Foo' => 'Bar',
            'Bar' => 'Baz',
        ),
        'Foo' => 'Bar',
        'Version' => '2.0',
    );

    private static $fallbackData = array(
        'Entries' => array(
            'Foo' => 'Foo',
            'Bam' => 'Lah',
        ),
        'Baz' => 'Foo',
        'Version' => '1.0',
    );

    private static $mergedData = array(
        // no recursive merging -> too complicated
        'Entries' => array(
            'Foo' => 'Bar',
            'Bar' => 'Baz',
        ),
        'Baz' => 'Foo',
        'Version' => '2.0',
        'Foo' => 'Bar',
    );

    protected function setUp(): void
    {
        $this->readerImpl = $this->getMockBuilder('Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface')->getMock();
        $this->reader = new BundleEntryReader($this->readerImpl);
    }

    public function testForwardCallToRead()
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->willReturn(self::$data);

        $this->assertSame(self::$data, $this->reader->read(self::RES_DIR, 'root'));
    }

    public function testReadEntireDataFileIfNoIndicesGiven()
    {
        $map = array('en' => self::$data, 'root' => self::$fallbackData);
        $this->readerImpl->expects($this->any())
            ->method('read')
            ->willReturnCallback(function ($dir, $locale) use ($map) {
                return $map[$locale] ?? array();
            });

        $this->assertSame(self::$mergedData, $this->reader->readEntry(self::RES_DIR, 'en', array()));
    }

    public function testReadExistingEntry()
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->willReturn(self::$data);

        $this->assertSame('Bar', $this->reader->readEntry(self::RES_DIR, 'root', array('Entries', 'Foo')));
    }

    /**
     */
    public function testReadNonExistingEntry()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MissingResourceException::class);

        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->willReturn(self::$data);

        $this->reader->readEntry(self::RES_DIR, 'root', array('Entries', 'NonExisting'));
    }

    public function testFallbackIfEntryDoesNotExist()
    {
        $map = array('en_GB' => self::$data, 'en' => self::$fallbackData);
        $this->readerImpl->expects($this->any())
            ->method('read')
            ->willReturnCallback(function ($dir, $locale) use ($map) {
                return $map[$locale] ?? array();
            });

        $this->assertSame('Lah', $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Entries', 'Bam')));
    }

    /**
     */
    public function testDontFallbackIfEntryDoesNotExistAndFallbackDisabled()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MissingResourceException::class);

        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willReturn(self::$data);

        $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Entries', 'Bam'), false);
    }

    public function testFallbackIfLocaleDoesNotExist()
    {
        $this->readerImpl->expects($this->any())
            ->method('read')
            ->willReturnCallback(function ($dir, $locale) {
                if ('en_GB' === $locale) {
                    throw new ResourceBundleNotFoundException();
                }
                return self::$fallbackData;
            });

        $this->assertSame('Lah', $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Entries', 'Bam')));
    }

    /**
     */
    public function testDontFallbackIfLocaleDoesNotExistAndFallbackDisabled()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MissingResourceException::class);

        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willThrowException(new ResourceBundleNotFoundException());

        $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Entries', 'Bam'), false);
    }

    public static function provideMergeableValues()
    {
        return array(
            array('foo', null, 'foo'),
            array(null, 'foo', 'foo'),
            array(array('foo', 'bar'), null, array('foo', 'bar')),
            array(array('foo', 'bar'), array(), array('foo', 'bar')),
            array(null, array('baz'), array('baz')),
            array(array(), array('baz'), array('baz')),
            array(array('foo', 'bar'), array('baz'), array('baz', 'foo', 'bar')),
        );
    }

    #[DataProvider('provideMergeableValues')]    public function testMergeDataWithFallbackData($childData, $parentData, $result)
    {
        if (null === $childData || \is_array($childData)) {
            $map = array('en' => $childData, 'root' => $parentData);
            $this->readerImpl->expects($this->any())
                ->method('read')
                ->willReturnCallback(function ($dir, $locale) use ($map) {
                    return $map[$locale] ?? array();
                });
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->willReturn($childData);
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en', array(), true));
    }

    #[DataProvider('provideMergeableValues')]    public function testDontMergeDataIfFallbackDisabled($childData, $parentData, $result)
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willReturn($childData);

        $this->assertSame($childData, $this->reader->readEntry(self::RES_DIR, 'en_GB', array(), false));
    }

    #[DataProvider('provideMergeableValues')]    public function testMergeExistingEntryWithExistingFallbackEntry($childData, $parentData, $result)
    {
        if (null === $childData || \is_array($childData)) {
            $map = array('en' => array('Foo' => array('Bar' => $childData)), 'root' => array('Foo' => array('Bar' => $parentData)));
            $this->readerImpl->expects($this->any())
                ->method('read')
                ->willReturnCallback(function ($dir, $locale) use ($map) {
                    return $map[$locale] ?? array();
                });
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->willReturn(array('Foo' => array('Bar' => $childData)));
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en', array('Foo', 'Bar'), true));
    }

    #[DataProvider('provideMergeableValues')]    public function testMergeNonExistingEntryWithExistingFallbackEntry($childData, $parentData, $result)
    {
        $map = array('en_GB' => array('Foo' => 'Baz'), 'en' => array('Foo' => array('Bar' => $parentData)));
        $this->readerImpl->expects($this->any())
            ->method('read')
            ->willReturnCallback(function ($dir, $locale) use ($map) {
                return $map[$locale] ?? array();
            });

        $this->assertSame($parentData, $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Foo', 'Bar'), true));
    }

    #[DataProvider('provideMergeableValues')]    public function testMergeExistingEntryWithNonExistingFallbackEntry($childData, $parentData, $result)
    {
        if (null === $childData || \is_array($childData)) {
            $map = array('en_GB' => array('Foo' => array('Bar' => $childData)), 'en' => array('Foo' => 'Bar'));
            $this->readerImpl->expects($this->any())
                ->method('read')
                ->willReturnCallback(function ($dir, $locale) use ($map) {
                    return $map[$locale] ?? array();
                });
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en_GB')
                ->willReturn(array('Foo' => array('Bar' => $childData)));
        }

        $this->assertSame($childData, $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Foo', 'Bar'), true));
    }

    /**
     */
    public function testFailIfEntryFoundNeitherInParentNorChild()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MissingResourceException::class);

        $map = array('en_GB' => array('Foo' => 'Baz'), 'en' => array('Foo' => 'Bar'));
        $this->readerImpl->expects($this->any())
            ->method('read')
            ->willReturnCallback(function ($dir, $locale) use ($map) {
                return $map[$locale] ?? array();
            });

        $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Foo', 'Bar'), true);
    }

    #[DataProvider('provideMergeableValues')]    public function testMergeTraversables($childData, $parentData, $result)
    {
        $parentData = \is_array($parentData) ? new \ArrayObject($parentData) : $parentData;
        $childData = \is_array($childData) ? new \ArrayObject($childData) : $childData;

        if (null === $childData || $childData instanceof \ArrayObject) {
            $map = array('en_GB' => array('Foo' => array('Bar' => $childData)), 'en' => array('Foo' => array('Bar' => $parentData)));
            $this->readerImpl->expects($this->any())
                ->method('read')
                ->willReturnCallback(function ($dir, $locale) use ($map) {
                    return $map[$locale] ?? array();
                });
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en_GB')
                ->willReturn(array('Foo' => array('Bar' => $childData)));
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Foo', 'Bar'), true));
    }

    #[DataProvider('provideMergeableValues')]    public function testFollowLocaleAliases($childData, $parentData, $result)
    {
        $this->reader->setLocaleAliases(array('mo' => 'ro_MD'));

        if (null === $childData || \is_array($childData)) {
            $map = array('ro_MD' => array('Foo' => array('Bar' => $childData)), 'ro' => array('Foo' => array('Bar' => $parentData)));
            $this->readerImpl->expects($this->any())
                ->method('read')
                ->willReturnCallback(function ($dir, $locale) use ($map) {
                    return $map[$locale] ?? array();
                });
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'ro_MD')
                ->willReturn(array('Foo' => array('Bar' => $childData)));
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'mo', array('Foo', 'Bar'), true));
    }
}
