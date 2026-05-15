<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\ChoiceList\Factory;


use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CachingFactoryDecoratorTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $decoratedFactory;

    /**
     * @var CachingFactoryDecorator
     */
    private $factory;

    protected function setUp(): void
    {
        $this->decoratedFactory = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface')->getMock();
        $this->factory = new CachingFactoryDecorator($this->decoratedFactory);
    }

    public function testCreateFromChoicesEmpty()
    {
        $list = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with(array())
            ->willReturn($list);

$this->assertSame($list, $this->factory->createListFromChoices(array()));
$this->assertSame($list, $this->factory->createListFromChoices(array()));
    }

    public function testCreateFromChoicesComparesTraversableChoicesAsArray()
    {
        // The top-most traversable is converted to an array
$choices1 = new \ArrayIterator(array('A' => 'a'));
        $choices2 = array('A' => 'a');
        $list = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices2)
            ->willReturn($list);

$this->assertSame($list, $this->factory->createListFromChoices($choices1));
$this->assertSame($list, $this->factory->createListFromChoices($choices2));
    }

    public function testCreateFromChoicesFlattensChoices()
    {
$choices1 = array('key' => array('A' => 'a'));
        $choices2 = array('A' => 'a');
        $list = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices1)
            ->willReturn($list);

$this->assertSame($list, $this->factory->createListFromChoices($choices1));
$this->assertSame($list, $this->factory->createListFromChoices($choices2));
    }

    #[DataProvider('provideSameChoices')]    public function testCreateFromChoicesSameChoices($choice1, $choice2)
    {
        $choices1 = array($choice1);
        $choices2 = array($choice2);
        $list = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices1)
            ->willReturn($list);

$this->assertSame($list, $this->factory->createListFromChoices($choices1));
$this->assertSame($list, $this->factory->createListFromChoices($choices2));
    }

    #[DataProvider('provideDistinguishedChoices')]    public function testCreateFromChoicesDifferentChoices($choice1, $choice2)
    {
        $choices1 = array($choice1);
        $choices2 = array($choice2);
        $list1 = new \stdClass();
        $list2 = new \stdClass();
        $this->decoratedFactory->expects($this->any())
            ->method('createListFromChoices')
            ->willReturnMap([
                [$choices1, $list1],
                [$choices2, $list2],
            ]);

$this->assertSame($list1, $this->factory->createListFromChoices($choices1));
$this->assertSame($list2, $this->factory->createListFromChoices($choices2));
    }

    public function testCreateFromChoicesSameValueClosure()
    {
        $choices = array(1);
        $list = new \stdClass();
        $closure = function () {};

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices, $closure)
            ->willReturn($list);

$this->assertSame($list, $this->factory->createListFromChoices($choices, $closure));
$this->assertSame($list, $this->factory->createListFromChoices($choices, $closure));
    }

    public function testCreateFromChoicesDifferentValueClosure()
    {
        $choices = array(1);
        $list1 = new \stdClass();
        $list2 = new \stdClass();
        $closure1 = function () {};
        $closure2 = function () {};
        $this->decoratedFactory->expects($this->any())
            ->method('createListFromChoices')
            ->willReturnMap([
                [$choices, $closure1, $list1],
                [$choices, $closure2, $list2],
            ]);

$this->assertSame($list1, $this->factory->createListFromChoices($choices, $closure1));
$this->assertSame($list2, $this->factory->createListFromChoices($choices, $closure2));
    }

    #[Group('legacy')]    public function testCreateFromFlippedChoicesEmpty()
    {
        $list = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromFlippedChoices')
            ->with(array())
            ->willReturn($list);

$this->assertSame($list, $this->factory->createListFromFlippedChoices(array()));
$this->assertSame($list, $this->factory->createListFromFlippedChoices(array()));
    }

    #[Group('legacy')]    public function testCreateFromFlippedChoicesComparesTraversableChoicesAsArray()
    {
        // The top-most traversable is converted to an array
$choices1 = new \ArrayIterator(array('a' => 'A'));
        $choices2 = array('a' => 'A');
        $list = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromFlippedChoices')
            ->with($choices2)
            ->willReturn($list);

$this->assertSame($list, $this->factory->createListFromFlippedChoices($choices1));
$this->assertSame($list, $this->factory->createListFromFlippedChoices($choices2));
    }

    #[Group('legacy')]    public function testCreateFromFlippedChoicesFlattensChoices()
    {
$choices1 = array('key' => array('a' => 'A'));
        $choices2 = array('a' => 'A');
        $list = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromFlippedChoices')
            ->with($choices1)
            ->willReturn($list);

$this->assertSame($list, $this->factory->createListFromFlippedChoices($choices1));
$this->assertSame($list, $this->factory->createListFromFlippedChoices($choices2));
    }

    #[DataProvider('provideSameKeyChoices')]
    #[Group('legacy')]
    /**
     */
    public function testCreateFromFlippedChoicesSameChoices($choice1, $choice2)
    {
        $choices1 = array($choice1 => 'A');
        $choices2 = array($choice2 => 'A');
        $list = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromFlippedChoices')
            ->with($choices1)
            ->willReturn($list);

$this->assertSame($list, $this->factory->createListFromFlippedChoices($choices1));
$this->assertSame($list, $this->factory->createListFromFlippedChoices($choices2));
    }

    #[DataProvider('provideDistinguishedKeyChoices')]
    #[Group('legacy')]
    /**
     */
    public function testCreateFromFlippedChoicesDifferentChoices($choice1, $choice2)
    {
        $choices1 = array($choice1 => 'A');
        $choices2 = array($choice2 => 'A');
        $list1 = new \stdClass();
        $list2 = new \stdClass();
        $this->decoratedFactory->expects($this->any())
            ->method('createListFromFlippedChoices')
            ->willReturnCallback(function ($choices) use ($choices1, $choices2, $list1, $list2) {
                return $choices === $choices1 ? $list1 : $list2;
            });

$this->assertSame($list1, $this->factory->createListFromFlippedChoices($choices1));
$this->assertSame($list2, $this->factory->createListFromFlippedChoices($choices2));
    }

    #[Group('legacy')]    public function testCreateFromFlippedChoicesSameValueClosure()
    {
        $choices = array(1);
        $list = new \stdClass();
        $closure = function () {};

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromFlippedChoices')
            ->with($choices, $closure)
            ->willReturn($list);

$this->assertSame($list, $this->factory->createListFromFlippedChoices($choices, $closure));
$this->assertSame($list, $this->factory->createListFromFlippedChoices($choices, $closure));
    }

    #[Group('legacy')]    public function testCreateFromFlippedChoicesDifferentValueClosure()
    {
        $choices = array(1);
        $list1 = new \stdClass();
        $list2 = new \stdClass();
        $closure1 = function () {};
        $closure2 = function () {};
        $this->decoratedFactory->expects($this->any())
            ->method('createListFromFlippedChoices')
            ->willReturnCallback(function ($c, $value) use ($list1, $list2, $closure1, $closure2) {
                return $value === $closure1 ? $list1 : $list2;
            });

$this->assertSame($list1, $this->factory->createListFromFlippedChoices($choices, $closure1));
$this->assertSame($list2, $this->factory->createListFromFlippedChoices($choices, $closure2));
    }

    public function testCreateFromLoaderSameLoader()
    {
        $loader = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();
        $list = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader)
            ->willReturn($list);

$this->assertSame($list, $this->factory->createListFromLoader($loader));
$this->assertSame($list, $this->factory->createListFromLoader($loader));
    }

    public function testCreateFromLoaderDifferentLoader()
    {
        $loader1 = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();
        $loader2 = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();
        $list1 = new \stdClass();
        $list2 = new \stdClass();
        $this->decoratedFactory->expects($this->any())
            ->method('createListFromLoader')
            ->willReturnMap([
                [$loader1, $list1],
                [$loader2, $list2],
            ]);

$this->assertSame($list1, $this->factory->createListFromLoader($loader1));
$this->assertSame($list2, $this->factory->createListFromLoader($loader2));
    }

    public function testCreateFromLoaderSameValueClosure()
    {
        $loader = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();
        $list = new \stdClass();
        $closure = function () {};

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader, $closure)
            ->willReturn($list);

$this->assertSame($list, $this->factory->createListFromLoader($loader, $closure));
$this->assertSame($list, $this->factory->createListFromLoader($loader, $closure));
    }

    public function testCreateFromLoaderDifferentValueClosure()
    {
        $loader = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();
        $list1 = new \stdClass();
        $list2 = new \stdClass();
        $closure1 = function () {};
        $closure2 = function () {};
        $this->decoratedFactory->expects($this->any())
            ->method('createListFromLoader')
            ->willReturnMap([
                [$loader, $closure1, $list1],
                [$loader, $closure2, $list2],
            ]);

$this->assertSame($list1, $this->factory->createListFromLoader($loader, $closure1));
$this->assertSame($list2, $this->factory->createListFromLoader($loader, $closure2));
    }

    public function testCreateViewSamePreferredChoices()
    {
        $preferred = array('a');
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, $preferred)
            ->willReturn($view);

$this->assertSame($view, $this->factory->createView($list, $preferred));
$this->assertSame($view, $this->factory->createView($list, $preferred));
    }

    public function testCreateViewDifferentPreferredChoices()
    {
        $preferred1 = array('a');
        $preferred2 = array('b');
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view1 = new \stdClass();
        $view2 = new \stdClass();
        $this->decoratedFactory->expects($this->any())
            ->method('createView')
            ->willReturnMap([
                [$list, $preferred1, $view1],
                [$list, $preferred2, $view2],
            ]);

$this->assertSame($view1, $this->factory->createView($list, $preferred1));
$this->assertSame($view2, $this->factory->createView($list, $preferred2));
    }

    public function testCreateViewSamePreferredChoicesClosure()
    {
        $preferred = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, $preferred)
            ->willReturn($view);

$this->assertSame($view, $this->factory->createView($list, $preferred));
$this->assertSame($view, $this->factory->createView($list, $preferred));
    }

    public function testCreateViewDifferentPreferredChoicesClosure()
    {
        $preferred1 = function () {};
        $preferred2 = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view1 = new \stdClass();
        $view2 = new \stdClass();
        $this->decoratedFactory->expects($this->any())
            ->method('createView')
            ->willReturnMap([
                [$list, $preferred1, $view1],
                [$list, $preferred2, $view2],
            ]);

$this->assertSame($view1, $this->factory->createView($list, $preferred1));
$this->assertSame($view2, $this->factory->createView($list, $preferred2));
    }

    public function testCreateViewSameLabelClosure()
    {
        $labels = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, $labels)
            ->willReturn($view);

$this->assertSame($view, $this->factory->createView($list, null, $labels));
$this->assertSame($view, $this->factory->createView($list, null, $labels));
    }

    public function testCreateViewDifferentLabelClosure()
    {
        $labels1 = function () {};
        $labels2 = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view1 = new \stdClass();
        $view2 = new \stdClass();
        $this->decoratedFactory->expects($this->any())
            ->method('createView')
            ->willReturnMap([
                [$list, null, $labels1, $view1],
                [$list, null, $labels2, $view2],
            ]);

$this->assertSame($view1, $this->factory->createView($list, null, $labels1));
$this->assertSame($view2, $this->factory->createView($list, null, $labels2));
    }

    public function testCreateViewSameIndexClosure()
    {
        $index = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, $index)
            ->willReturn($view);

$this->assertSame($view, $this->factory->createView($list, null, null, $index));
$this->assertSame($view, $this->factory->createView($list, null, null, $index));
    }

    public function testCreateViewDifferentIndexClosure()
    {
        $index1 = function () {};
        $index2 = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view1 = new \stdClass();
        $view2 = new \stdClass();
        $this->decoratedFactory->expects($this->any())
            ->method('createView')
            ->willReturnMap([
                [$list, null, null, $index1, $view1],
                [$list, null, null, $index2, $view2],
            ]);

$this->assertSame($view1, $this->factory->createView($list, null, null, $index1));
$this->assertSame($view2, $this->factory->createView($list, null, null, $index2));
    }

    public function testCreateViewSameGroupByClosure()
    {
        $groupBy = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, $groupBy)
            ->willReturn($view);

$this->assertSame($view, $this->factory->createView($list, null, null, null, $groupBy));
$this->assertSame($view, $this->factory->createView($list, null, null, null, $groupBy));
    }

    public function testCreateViewDifferentGroupByClosure()
    {
        $groupBy1 = function () {};
        $groupBy2 = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view1 = new \stdClass();
        $view2 = new \stdClass();
        $this->decoratedFactory->expects($this->any())
            ->method('createView')
            ->willReturnMap([
                [$list, null, null, null, $groupBy1, $view1],
                [$list, null, null, null, $groupBy2, $view2],
            ]);

$this->assertSame($view1, $this->factory->createView($list, null, null, null, $groupBy1));
$this->assertSame($view2, $this->factory->createView($list, null, null, null, $groupBy2));
    }

    public function testCreateViewSameAttributes()
    {
        $attr = array('class' => 'foobar');
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, null, $attr)
            ->willReturn($view);

$this->assertSame($view, $this->factory->createView($list, null, null, null, null, $attr));
$this->assertSame($view, $this->factory->createView($list, null, null, null, null, $attr));
    }

    public function testCreateViewDifferentAttributes()
    {
        $attr1 = array('class' => 'foobar1');
        $attr2 = array('class' => 'foobar2');
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view1 = new \stdClass();
        $view2 = new \stdClass();
        $this->decoratedFactory->expects($this->any())
            ->method('createView')
            ->willReturnMap([
                [$list, null, null, null, null, $attr1, $view1],
                [$list, null, null, null, null, $attr2, $view2],
            ]);

$this->assertSame($view1, $this->factory->createView($list, null, null, null, null, $attr1));
$this->assertSame($view2, $this->factory->createView($list, null, null, null, null, $attr2));
    }

    public function testCreateViewSameAttributesClosure()
    {
        $attr = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view = new \stdClass();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, null, $attr)
            ->willReturn($view);

$this->assertSame($view, $this->factory->createView($list, null, null, null, null, $attr));
$this->assertSame($view, $this->factory->createView($list, null, null, null, null, $attr));
    }

    public function testCreateViewDifferentAttributesClosure()
    {
        $attr1 = function () {};
        $attr2 = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view1 = new \stdClass();
        $view2 = new \stdClass();
        $this->decoratedFactory->expects($this->any())
            ->method('createView')
            ->willReturnMap([
                [$list, null, null, null, null, $attr1, $view1],
                [$list, null, null, null, null, $attr2, $view2],
            ]);

$this->assertSame($view1, $this->factory->createView($list, null, null, null, null, $attr1));
$this->assertSame($view2, $this->factory->createView($list, null, null, null, null, $attr2));
    }

    public static function provideSameChoices()
    {
        $object = (object) array('foo' => 'bar');

        return array(
            array(0, 0),
            array('a', 'a'),
            // https://github.com/symfony/symfony/issues/10409
            array(\chr(181).'meter', \chr(181).'meter'), // UTF-8
            array($object, $object),
        );
    }

    public static function provideDistinguishedChoices()
    {
        return array(
            array(0, false),
            array(0, null),
            array(0, '0'),
            array(0, ''),
            array(1, true),
            array(1, '1'),
            array(1, 'a'),
            array('', false),
            array('', null),
            array(false, null),
            // Same properties, but not identical
            array((object) array('foo' => 'bar'), (object) array('foo' => 'bar')),
        );
    }

    public static function provideSameKeyChoices()
    {
        // Only test types here that can be used as array keys
        return array(
            array(0, 0),
            array(0, '0'),
            array('a', 'a'),
            array(\chr(181).'meter', \chr(181).'meter'),
        );
    }

    public static function provideDistinguishedKeyChoices()
    {
        // Only test types here that can be used as array keys
        return array(
            array(0, ''),
            array(1, 'a'),
            array('', 'a'),
        );
    }
}
