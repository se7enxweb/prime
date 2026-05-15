<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;


use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\ResolvedFormType;
use Symfony\Component\Form\ResolvedFormTypeFactoryInterface;
use Symfony\Component\Form\Tests\Fixtures\FooSubType;
use Symfony\Component\Form\Tests\Fixtures\FooType;
use Symfony\Component\Form\Tests\Fixtures\FooTypeBarExtension;
use Symfony\Component\Form\Tests\Fixtures\FooTypeBazExtension;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooSubType;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooSubTypeWithParentInstance;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooType;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooTypeBarExtension;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooTypeBazExtension;
use Symfony\Component\Form\Tests\Fixtures\TestExtension;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormRegistryTest extends TestCase
{
    /**
     * @var FormRegistry
     */
    private $registry;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|ResolvedFormTypeFactoryInterface
     */
    private $resolvedTypeFactory;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $guesser1;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $guesser2;

    /**
     * @var TestExtension
     */
    private $extension1;

    /**
     * @var TestExtension
     */
    private $extension2;

    protected function setUp(): void
    {
        $this->resolvedTypeFactory = $this->getMockBuilder('Symfony\Component\Form\ResolvedFormTypeFactory')->getMock();
        $this->guesser1 = $this->getMockBuilder('Symfony\Component\Form\FormTypeGuesserInterface')->getMock();
        $this->guesser2 = $this->getMockBuilder('Symfony\Component\Form\FormTypeGuesserInterface')->getMock();
        $this->extension1 = new TestExtension($this->guesser1);
        $this->extension2 = new TestExtension($this->guesser2);
        $this->registry = new FormRegistry(array(
            $this->extension1,
            $this->extension2,
        ), $this->resolvedTypeFactory);
    }

    public function testGetTypeFromExtension()
    {
        $type = new FooType();
        $resolvedType = new ResolvedFormType($type);

        $this->extension2->addType($type);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type)
            ->willReturn($resolvedType);

$this->assertSame($resolvedType, $this->registry->getType(\get_class($type)));
    }

    public function testLoadUnregisteredType()
    {
        $type = new FooType();
        $resolvedType = new ResolvedFormType($type);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type)
            ->willReturn($resolvedType);

$this->assertSame($resolvedType, $this->registry->getType('Symfony\Component\Form\Tests\Fixtures\FooType'));
    }

    /**
     */
    public function testFailIfUnregisteredTypeNoClass()
    {
        $this->expectException(\Symfony\Component\Form\Exception\InvalidArgumentException::class);

        $this->registry->getType('Symfony\Blubb');
    }

    /**
     */
    public function testFailIfUnregisteredTypeNoFormType()
    {
        $this->expectException(\Symfony\Component\Form\Exception\InvalidArgumentException::class);

        $this->registry->getType('stdClass');
    }

    #[Group('legacy')]    public function testLegacyGetTypeFromExtension()
    {
        $type = new LegacyFooType();
        $resolvedType = new ResolvedFormType($type);

        $this->extension2->addType($type);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type)
            ->willReturn($resolvedType);

$this->assertSame($resolvedType, $this->registry->getType('foo'));

        // Even types with explicit getName() methods must support access by
        // FQCN to support a smooth transition from 2.8 => 3.0
$this->assertSame($resolvedType, $this->registry->getType(\get_class($type)));
    }

    public function testGetTypeWithTypeExtensions()
    {
        $type = new FooType();
        $ext1 = new FooTypeBarExtension();
        $ext2 = new FooTypeBazExtension();
        $resolvedType = new ResolvedFormType($type, array($ext1, $ext2));

        $this->extension2->addType($type);
        $this->extension1->addTypeExtension($ext1);
        $this->extension2->addTypeExtension($ext2);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type, array($ext1, $ext2))
            ->willReturn($resolvedType);

$this->assertSame($resolvedType, $this->registry->getType(\get_class($type)));
    }

    #[Group('legacy')]    public function testLegacyGetTypeWithTypeExtensions()
    {
        $type = new LegacyFooType();
        $ext1 = new LegacyFooTypeBarExtension();
        $ext2 = new LegacyFooTypeBazExtension();
$resolvedType = new ResolvedFormType($type, array($ext1, $ext2));

        $this->extension2->addType($type);
        $this->extension1->addTypeExtension($ext1);
        $this->extension2->addTypeExtension($ext2);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type, array($ext1, $ext2))
            ->willReturn($resolvedType);

$this->assertSame($resolvedType, $this->registry->getType('foo'));
    }

    public function testGetTypeConnectsParent()
    {
        $parentType = new FooType();
        $type = new FooSubType();
        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type);

        $this->extension1->addType($parentType);
        $this->extension2->addType($type);

        $this->resolvedTypeFactory->expects($this->any())
            ->method('createResolvedType')
            ->willReturnCallback(function ($t) use ($parentType, $type, $parentResolvedType, $resolvedType) {
                if ($t === $parentType) {
                    return $parentResolvedType;
                }
                if ($t === $type) {
                    return $resolvedType;
                }
            });

$this->assertSame($resolvedType, $this->registry->getType(\get_class($type)));
    }

    #[Group('legacy')]    public function testLegacyGetTypeConnectsParent()
    {
        $parentType = new LegacyFooType();
        $type = new LegacyFooSubType();
        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type);

        $this->extension1->addType($parentType);
        $this->extension2->addType($type);

        $this->resolvedTypeFactory->expects($this->any())
            ->method('createResolvedType')
            ->willReturnCallback(function ($t) use ($parentType, $type, $parentResolvedType, $resolvedType) {
                if ($t === $parentType) {
                    return $parentResolvedType;
                }
                if ($t === $type) {
                    return $resolvedType;
                }
            });

$this->assertSame($resolvedType, $this->registry->getType('foo_sub_type'));
    }

    #[Group('legacy')]    public function testGetTypeConnectsParentIfGetParentReturnsInstance()
    {
        $type = new LegacyFooSubTypeWithParentInstance();
        $parentResolvedType = new ResolvedFormType($type->getParent());
        $resolvedType = new ResolvedFormType($type);

        $this->extension1->addType($type);

        $parentTypeInstance = $type->getParent();
        $this->resolvedTypeFactory->expects($this->any())
            ->method('createResolvedType')
            ->willReturnCallback(function ($t) use ($parentTypeInstance, $type, $parentResolvedType, $resolvedType) {
                if ($t === $parentTypeInstance || (is_object($t) && get_class($t) === get_class($parentTypeInstance))) {
                    return $parentResolvedType;
                }
                if ($t === $type) {
                    return $resolvedType;
                }
            });

$this->assertSame($resolvedType, $this->registry->getType('foo_sub_type_parent_instance'));
    }

    /**
     */
    public function testGetTypeThrowsExceptionIfTypeNotFound()
    {
        $this->expectException(\Symfony\Component\Form\Exception\InvalidArgumentException::class);

        $this->registry->getType('bar');
    }

    public function testHasTypeAfterLoadingFromExtension()
    {
        $type = new FooType();
        $resolvedType = new ResolvedFormType($type);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type)
            ->willReturn($resolvedType);

        $this->extension2->addType($type);

$this->assertTrue($this->registry->hasType(\get_class($type)));
    }

    public function testHasTypeIfFQCN()
    {
$this->assertTrue($this->registry->hasType('Symfony\Component\Form\Tests\Fixtures\FooType'));
    }

    public function testDoesNotHaveTypeIfNonExistingClass()
    {
        $this->assertFalse($this->registry->hasType('Symfony\Blubb'));
    }

    public function testDoesNotHaveTypeIfNoFormType()
    {
        $this->assertFalse($this->registry->hasType('stdClass'));
    }

    #[Group('legacy')]    public function testLegacyHasTypeAfterLoadingFromExtension()
    {
        $type = new LegacyFooType();
        $resolvedType = new ResolvedFormType($type);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type)
            ->willReturn($resolvedType);

        $this->extension2->addType($type);

$this->assertTrue($this->registry->hasType('foo'));
    }

    public function testGetTypeGuesser()
    {
$expectedGuesser = new FormTypeGuesserChain(array($this->guesser1, $this->guesser2));

        $this->assertEquals($expectedGuesser, $this->registry->getTypeGuesser());

        $registry = new FormRegistry(
            array($this->getMockBuilder('Symfony\Component\Form\FormExtensionInterface')->getMock()),
            $this->resolvedTypeFactory
        );

        $this->assertNull($registry->getTypeGuesser());
    }

    public function testGetExtensions()
    {
        $expectedExtensions = array($this->extension1, $this->extension2);

        $this->assertEquals($expectedExtensions, $this->registry->getExtensions());
    }
}
