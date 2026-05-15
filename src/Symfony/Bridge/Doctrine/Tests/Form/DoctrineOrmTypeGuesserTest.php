<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Form;


use PHPUnit\Framework\Attributes\DataProvider;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmTypeGuesser;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\ValueGuess;

class DoctrineOrmTypeGuesserTest extends TestCase
{
    #[DataProvider('requiredProvider')]    public function testRequiredGuesser($classMetadata, $expected)
    {
        $this->assertEquals($expected, $this->getGuesser($classMetadata)->guessRequired('TestEntity', 'field'));
    }

    public static function requiredProvider()
    {
        $gen = new \PHPUnit\Framework\MockObject\Generator\Generator();
        $allMethods = array('isNullable', 'isAssociationWithSingleJoinColumn', 'getAssociationMapping', 'getTypeOfField', 'getIdentifierFieldNames', 'hasField', 'hasAssociation', 'isSingleValuedAssociation', 'isCollectionValuedAssociation', 'getFieldNames', 'getIdentifier', 'getReflectionClass', 'isIdentifier', 'getAssociationNames', 'getAssociationTargetClass', 'isAssociationInverseSide', 'getAssociationMappedByTargetField');
        $return = array();

        // Simple field, not nullable
        $classMetadata = $gen->testDouble('Doctrine\ORM\Mapping\ClassMetadata', true, false, $allMethods, array(), '', false);
        $classMetadata->fieldMappings['field'] = true;
        $classMetadata->method('isNullable')->willReturn(false);

$return[] = array($classMetadata, new ValueGuess(true, Guess::HIGH_CONFIDENCE));

        // Simple field, nullable
        $classMetadata = $gen->testDouble('Doctrine\ORM\Mapping\ClassMetadata', true, false, $allMethods, array(), '', false);
        $classMetadata->fieldMappings['field'] = true;
        $classMetadata->method('isNullable')->willReturn(true);

$return[] = array($classMetadata, new ValueGuess(false, Guess::MEDIUM_CONFIDENCE));

        // One-to-one, nullable (by default)
        $classMetadata = $gen->testDouble('Doctrine\ORM\Mapping\ClassMetadata', true, false, $allMethods, array(), '', false);
        $classMetadata->method('isAssociationWithSingleJoinColumn')->willReturn(true);

$mapping = array('joinColumns' => array(array()));
        $classMetadata->method('getAssociationMapping')->willReturn($mapping);

$return[] = array($classMetadata, new ValueGuess(false, Guess::HIGH_CONFIDENCE));

        // One-to-one, nullable (explicit)
        $classMetadata = $gen->testDouble('Doctrine\ORM\Mapping\ClassMetadata', true, false, $allMethods, array(), '', false);
        $classMetadata->method('isAssociationWithSingleJoinColumn')->willReturn(true);

$mapping = array('joinColumns' => array(array('nullable' => true)));
        $classMetadata->method('getAssociationMapping')->willReturn($mapping);

$return[] = array($classMetadata, new ValueGuess(false, Guess::HIGH_CONFIDENCE));

        // One-to-one, not nullable
        $classMetadata = $gen->testDouble('Doctrine\ORM\Mapping\ClassMetadata', true, false, $allMethods, array(), '', false);
        $classMetadata->method('isAssociationWithSingleJoinColumn')->willReturn(true);

$mapping = array('joinColumns' => array(array('nullable' => false)));
        $classMetadata->method('getAssociationMapping')->willReturn($mapping);

$return[] = array($classMetadata, new ValueGuess(true, Guess::HIGH_CONFIDENCE));

        // One-to-many, no clue
        $classMetadata = $gen->testDouble('Doctrine\ORM\Mapping\ClassMetadata', true, false, $allMethods, array(), '', false);
        $classMetadata->method('isAssociationWithSingleJoinColumn')->willReturn(false);

        $return[] = array($classMetadata, null);

        return $return;
    }

    private function getGuesser(ClassMetadata $classMetadata)
    {
        $em = $this->getMockBuilder('Doctrine\Common\Persistence\ObjectManager')->getMock();
        $em->expects($this->once())->method('getClassMetaData')->with('TestEntity')->willReturn($classMetadata);

        $registry = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')->getMock();
$registry->expects($this->once())->method('getManagers')->willReturn(array($em));

        return new DoctrineOrmTypeGuesser($registry);
    }
}
