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
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooSubType;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooSubTypeWithParentInstance;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooType;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormFactoryTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $guesser1;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $guesser2;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $registry;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $resolvedTypeFactory;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $builder;

    /**
     * @var FormFactory
     */
    private $factory;

    protected function setUp(): void
    {
        $this->resolvedTypeFactory = $this->getMockBuilder('Symfony\Component\Form\ResolvedFormTypeFactoryInterface')->getMock();
        $this->guesser1 = $this->getMockBuilder('Symfony\Component\Form\FormTypeGuesserInterface')->getMock();
        $this->guesser2 = $this->getMockBuilder('Symfony\Component\Form\FormTypeGuesserInterface')->getMock();
        $this->registry = $this->getMockBuilder('Symfony\Component\Form\FormRegistryInterface')->getMock();
        $this->builder = $this->getMockBuilder('Symfony\Component\Form\Test\FormBuilderInterface')->getMock();
        $this->factory = new FormFactory($this->registry, $this->resolvedTypeFactory);

        $this->registry->expects($this->any())
            ->method('getTypeGuesser')
            ->willReturn(new FormTypeGuesserChain(array(
                $this->guesser1,
                $this->guesser2,
)));
    }

    public function testCreateNamedBuilderWithTypeName()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('type')
            ->willReturn($resolvedType);

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

$this->assertSame($this->builder, $this->factory->createNamedBuilder('name', 'type', null, $options));
    }

    #[Group('legacy')]    public function testCreateNamedBuilderWithTypeInstance()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $type = new LegacyFooType();
        $resolvedType = $this->getMockResolvedType();

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type)
            ->willReturn($resolvedType);

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

$this->assertSame($this->builder, $this->factory->createNamedBuilder('name', $type, null, $options));
    }

    #[Group('legacy')]    public function testCreateNamedBuilderWithTypeInstanceWithParentType()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $type = new LegacyFooSubType();
        $resolvedType = $this->getMockResolvedType();
        $parentResolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('foo')
            ->willReturn($parentResolvedType);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type, array(), $parentResolvedType)
            ->willReturn($resolvedType);

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

$this->assertSame($this->builder, $this->factory->createNamedBuilder('name', $type, null, $options));
    }

    #[Group('legacy')]    public function testCreateNamedBuilderWithTypeInstanceWithParentTypeInstance()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $type = new LegacyFooSubTypeWithParentInstance();
        $resolvedType = $this->getMockResolvedType();
        $parentResolvedType = $this->getMockResolvedType();

        $this->resolvedTypeFactory->expects($this->any())
            ->method('createResolvedType')
            ->willReturnCallback(function ($t, $ext = array(), $parent = null) use ($type, $resolvedType, $parentResolvedType) {
                if ($t instanceof \Symfony\Component\Form\Tests\Fixtures\LegacyFooSubTypeWithParentInstance) {
                    return $resolvedType;
                }
                return $parentResolvedType;
            });

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

$this->assertSame($this->builder, $this->factory->createNamedBuilder('name', $type, null, $options));
    }

    #[Group('legacy')]    public function testCreateNamedBuilderWithResolvedTypeInstance()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

$this->assertSame($this->builder, $this->factory->createNamedBuilder('name', $resolvedType, null, $options));
    }

    public function testCreateNamedBuilderFillsDataOption()
    {
        $givenOptions = array('a' => '1', 'b' => '2');
        $expectedOptions = array_merge($givenOptions, array('data' => 'DATA'));
        $resolvedOptions = array('a' => '2', 'b' => '3', 'data' => 'DATA');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('type')
            ->willReturn($resolvedType);

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $expectedOptions)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

$this->assertSame($this->builder, $this->factory->createNamedBuilder('name', 'type', 'DATA', $givenOptions));
    }

    public function testCreateNamedBuilderDoesNotOverrideExistingDataOption()
    {
        $options = array('a' => '1', 'b' => '2', 'data' => 'CUSTOM');
        $resolvedOptions = array('a' => '2', 'b' => '3', 'data' => 'CUSTOM');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('type')
            ->willReturn($resolvedType);

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

$this->assertSame($this->builder, $this->factory->createNamedBuilder('name', 'type', 'DATA', $options));
    }

    /**
     */
    public function testCreateNamedBuilderThrowsUnderstandableException()
    {
        $this->expectException(\Symfony\Component\Form\Exception\UnexpectedTypeException::class);
        $this->expectExceptionMessage('Expected argument of type "string, Symfony\\Component\\Form\\ResolvedFormTypeInterface or Symfony\\Component\\Form\\FormTypeInterface", "stdClass" given');

        $this->factory->createNamedBuilder('name', new \stdClass());
    }

    /**
     */
    public function testCreateThrowsUnderstandableException()
    {
        $this->expectException(\Symfony\Component\Form\Exception\UnexpectedTypeException::class);
        $this->expectExceptionMessage('Expected argument of type "string, Symfony\\Component\\Form\\ResolvedFormTypeInterface or Symfony\\Component\\Form\\FormTypeInterface", "stdClass" given');

        $this->factory->create(new \stdClass());
    }

    public function testCreateUsesBlockPrefixIfTypeGivenAsString()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');

        // the interface does not have the method, so use the real class
        $resolvedType = $this->getMockBuilder('Symfony\Component\Form\ResolvedFormType')
            ->disableOriginalConstructor()
            ->getMock();

        $resolvedType->expects($this->any())
            ->method('getBlockPrefix')
            ->willReturn('TYPE_PREFIX');

        $this->registry->expects($this->any())
            ->method('getType')
            ->with('TYPE')
            ->willReturn($resolvedType);

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'TYPE_PREFIX', $options)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->builder->expects($this->once())
            ->method('getForm')
            ->willReturn('FORM');

$this->assertSame('FORM', $this->factory->create('TYPE', null, $options));
    }

    #[Group('legacy')]    public function testCreateUsesTypeNameIfTypeGivenAsString()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->any())
            ->method('getType')
            ->with('TYPE')
            ->willReturn($resolvedType);

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'TYPE', $options)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->builder->expects($this->once())
            ->method('getForm')
            ->willReturn('FORM');

$this->assertSame('FORM', $this->factory->create('TYPE', null, $options));
    }

    #[Group('legacy')]    public function testCreateStripsNamespaceOffTypeName()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->any())
            ->method('getType')
            ->with('Vendor\Name\Space\UserForm')
            ->willReturn($resolvedType);

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'user_form', $options)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->builder->expects($this->once())
            ->method('getForm')
            ->willReturn('FORM');

$this->assertSame('FORM', $this->factory->create('Vendor\Name\Space\UserForm', null, $options));
    }

    #[Group('legacy')]    public function testLegacyCreateStripsNamespaceOffTypeNameAccessByFQCN()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->any())
            ->method('getType')
            ->with('userform')
            ->willReturn($resolvedType);

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'userform', $options)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->builder->expects($this->once())
            ->method('getForm')
            ->willReturn('FORM');

$this->assertSame('FORM', $this->factory->create('userform', null, $options));
    }

    #[Group('legacy')]    public function testCreateStripsTypeSuffixOffTypeName()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->any())
            ->method('getType')
            ->with('Vendor\Name\Space\UserType')
            ->willReturn($resolvedType);

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'user', $options)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->builder->expects($this->once())
            ->method('getForm')
            ->willReturn('FORM');

$this->assertSame('FORM', $this->factory->create('Vendor\Name\Space\UserType', null, $options));
    }

    #[Group('legacy')]    public function testCreateDoesNotStripTypeSuffixIfResultEmpty()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->any())
            ->method('getType')
            ->with('Vendor\Name\Space\Type')
            ->willReturn($resolvedType);

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'type', $options)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->builder->expects($this->once())
            ->method('getForm')
            ->willReturn('FORM');

$this->assertSame('FORM', $this->factory->create('Vendor\Name\Space\Type', null, $options));
    }

    #[Group('legacy')]    public function testCreateConvertsTypeToUnderscoreSyntax()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->any())
            ->method('getType')
            ->with('Vendor\Name\Space\MyProfileHTMLType')
            ->willReturn($resolvedType);

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'my_profile_html', $options)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->builder->expects($this->once())
            ->method('getForm')
            ->willReturn('FORM');

$this->assertSame('FORM', $this->factory->create('Vendor\Name\Space\MyProfileHTMLType', null, $options));
    }

    #[Group('legacy')]    public function testCreateUsesTypeNameIfTypeGivenAsObject()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $resolvedType->expects($this->once())
            ->method('getName')
            ->willReturn('TYPE');

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'TYPE', $options)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->builder->expects($this->once())
            ->method('getForm')
            ->willReturn('FORM');

$this->assertSame('FORM', $this->factory->create($resolvedType, null, $options));
    }

    public function testCreateNamed()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('type')
            ->willReturn($resolvedType);

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->willReturn($this->builder);

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->willReturn($resolvedOptions);

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->builder->expects($this->once())
            ->method('getForm')
            ->willReturn('FORM');

$this->assertSame('FORM', $this->factory->createNamed('name', 'type', null, $options));
    }

    public function testCreateBuilderForPropertyWithoutTypeGuesser()
    {
        $registry = $this->getMockBuilder('Symfony\Component\Form\FormRegistryInterface')->getMock();
        $factory = $this->getMockBuilder('Symfony\Component\Form\FormFactory')
            ->onlyMethods(array('createNamedBuilder'))
            ->setConstructorArgs(array($registry, $this->resolvedTypeFactory))
            ->getMock();

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array())
            ->willReturn('builderInstance');

        $this->builder = $factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertEquals('builderInstance', $this->builder);
    }

    public function testCreateBuilderForPropertyCreatesFormWithHighestConfidence()
    {
        $this->guesser1->expects($this->once())
            ->method('guessType')
            ->with('Application\Author', 'firstName')
            ->willReturn(new TypeGuess(
                'Symfony\Component\Form\Extension\Core\Type\TextType',
                array('attr' => array('maxlength' => 10)),
                Guess::MEDIUM_CONFIDENCE
));

        $this->guesser2->expects($this->once())
            ->method('guessType')
            ->with('Application\Author', 'firstName')
            ->willReturn(new TypeGuess(
                'Symfony\Component\Form\Extension\Core\Type\PasswordType',
                array('attr' => array('maxlength' => 7)),
                Guess::HIGH_CONFIDENCE
));

$factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\PasswordType', null, array('attr' => array('maxlength' => 7)))
            ->willReturn('builderInstance');

        $this->builder = $factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertEquals('builderInstance', $this->builder);
    }

    public function testCreateBuilderCreatesTextFormIfNoGuess()
    {
        $this->guesser1->expects($this->once())
            ->method('guessType')
            ->with('Application\Author', 'firstName')
            ->willReturn(null);

$factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->willReturn('builderInstance');

        $this->builder = $factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertEquals('builderInstance', $this->builder);
    }

    public function testOptionsCanBeOverridden()
    {
        $this->guesser1->expects($this->once())
            ->method('guessType')
            ->with('Application\Author', 'firstName')
            ->willReturn(new TypeGuess(
                'Symfony\Component\Form\Extension\Core\Type\TextType',
                array('attr' => array('class' => 'foo', 'maxlength' => 10)),
                Guess::MEDIUM_CONFIDENCE
));

$factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array('attr' => array('class' => 'foo', 'maxlength' => 11)))
            ->willReturn('builderInstance');

        $this->builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName',
            null,
            array('attr' => array('maxlength' => 11))
        );

        $this->assertEquals('builderInstance', $this->builder);
    }

    public function testCreateBuilderUsesMaxLengthIfFound()
    {
        $this->guesser1->expects($this->once())
            ->method('guessMaxLength')
            ->with('Application\Author', 'firstName')
            ->willReturn(new ValueGuess(
                    15,
                    Guess::MEDIUM_CONFIDENCE
));

        $this->guesser2->expects($this->once())
            ->method('guessMaxLength')
            ->with('Application\Author', 'firstName')
            ->willReturn(new ValueGuess(
                    20,
                    Guess::HIGH_CONFIDENCE
));

$factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array('attr' => array('maxlength' => 20)))
            ->willReturn('builderInstance');

        $this->builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName'
        );

        $this->assertEquals('builderInstance', $this->builder);
    }

    public function testCreateBuilderUsesMaxLengthAndPattern()
    {
        $this->guesser1->expects($this->once())
            ->method('guessMaxLength')
            ->with('Application\Author', 'firstName')
            ->willReturn(new ValueGuess(
                20,
                Guess::HIGH_CONFIDENCE
));

        $this->guesser2->expects($this->once())
            ->method('guessPattern')
            ->with('Application\Author', 'firstName')
            ->willReturn(new ValueGuess(
                '.{5,}',
                Guess::HIGH_CONFIDENCE
));

$factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array('attr' => array('maxlength' => 20, 'pattern' => '.{5,}', 'class' => 'tinymce')))
            ->willReturn('builderInstance');

        $this->builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName',
            null,
            array('attr' => array('class' => 'tinymce'))
        );

        $this->assertEquals('builderInstance', $this->builder);
    }

    public function testCreateBuilderUsesRequiredSettingWithHighestConfidence()
    {
        $this->guesser1->expects($this->once())
            ->method('guessRequired')
            ->with('Application\Author', 'firstName')
            ->willReturn(new ValueGuess(
                    true,
                    Guess::MEDIUM_CONFIDENCE
));

        $this->guesser2->expects($this->once())
            ->method('guessRequired')
            ->with('Application\Author', 'firstName')
            ->willReturn(new ValueGuess(
                    false,
                    Guess::HIGH_CONFIDENCE
));

$factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array('required' => false))
            ->willReturn('builderInstance');

        $this->builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName'
        );

        $this->assertEquals('builderInstance', $this->builder);
    }

    public function testCreateBuilderUsesPatternIfFound()
    {
        $this->guesser1->expects($this->once())
            ->method('guessPattern')
            ->with('Application\Author', 'firstName')
            ->willReturn(new ValueGuess(
                    '[a-z]',
                    Guess::MEDIUM_CONFIDENCE
));

        $this->guesser2->expects($this->once())
            ->method('guessPattern')
            ->with('Application\Author', 'firstName')
            ->willReturn(new ValueGuess(
                    '[a-zA-Z]',
                    Guess::HIGH_CONFIDENCE
));

$factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array('attr' => array('pattern' => '[a-zA-Z]')))
            ->willReturn('builderInstance');

        $this->builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName'
        );

        $this->assertEquals('builderInstance', $this->builder);
    }

    private function getMockFactory(array $methods = array())
    {
        return $this->getMockBuilder('Symfony\Component\Form\FormFactory')
            ->onlyMethods($methods)
            ->setConstructorArgs(array($this->registry, $this->resolvedTypeFactory))
            ->getMock();
    }

    private function getMockResolvedType()
    {
        return $this->getMockBuilder('Symfony\Component\Form\ResolvedFormTypeInterface')->getMock();
    }
}
