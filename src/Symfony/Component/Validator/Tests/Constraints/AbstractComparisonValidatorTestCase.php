<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Intl\Util\IntlTestHelper;
use Symfony\Component\Validator\Constraint;

class ComparisonTest_Class
{
    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        return (string) $this->value;
    }
}

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
abstract class AbstractComparisonValidatorTestCase extends AbstractConstraintValidatorTest
{
    protected static function addPhp5Dot5Comparisons(array $comparisons)
    {
        if (\PHP_VERSION_ID < 50500) {
            return $comparisons;
        }

        $result = $comparisons;

        // Duplicate all tests involving DateTime objects to be tested with
        // DateTimeImmutable objects as well
        foreach ($comparisons as $comparison) {
            $add = false;

            foreach ($comparison as $i => $value) {
                if ($value instanceof \DateTime) {
                    $comparison[$i] = new \DateTimeImmutable(
                        $value->format('Y-m-d H:i:s.u e'),
                        $value->getTimezone()
                    );
                    $add = true;
                } elseif ('DateTime' === $value) {
                    $comparison[$i] = 'DateTimeImmutable';
                    $add = true;
                }
            }

            if ($add) {
                $result[] = $comparison;
            }
        }

        return $result;
    }

    public static function provideInvalidConstraintOptions()
    {
        return array(
            array(null),
            array(array()),
        );
    }

    #[DataProvider('provideInvalidConstraintOptions')]
    public function testThrowsConstraintExceptionIfNoValueOrProperty($options)
    {
        $this->expectException(\Symfony\Component\Validator\Exception\ConstraintDefinitionException::class);

        $this->createConstraint($options);
    }

    /**
     * @param mixed $dirtyValue
     * @param mixed $comparisonValue
     */
    #[DataProvider('provideAllValidComparisons')]
    public function testValidComparisonToValue($dirtyValue, $comparisonValue)
    {
        $constraint = $this->createConstraint(array('value' => $comparisonValue));

        $this->validator->validate($dirtyValue, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @return array
     */
    public static function provideAllValidComparisons()
    {
        // The provider runs before setUp(), so we need to manually fix
        // the default timezone
        $timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $comparisons = self::addPhp5Dot5Comparisons(static::provideValidComparisons());

        date_default_timezone_set($timezone);

        return $comparisons;
    }

    /**
     * @return array
     */
    abstract public static function provideValidComparisons();

    /**
     * @param mixed  $dirtyValue
     * @param mixed  $dirtyValueAsString
     * @param mixed  $comparedValue
     * @param mixed  $comparedValueString
     * @param string $comparedValueType
     */
    #[DataProvider('provideAllInvalidComparisons')]
    public function testInvalidComparisonToValue($dirtyValue, $dirtyValueAsString, $comparedValue, $comparedValueString, $comparedValueType)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        if ($dirtyValue instanceof \DateTime || $dirtyValue instanceof \DateTimeInterface) {
            IntlTestHelper::requireIntl($this, '57.1');

            if (\PHP_VERSION_ID < 50304 && !(\extension_loaded('intl') && method_exists('IntlDateFormatter', 'setTimeZone'))) {
                $this->markTestSkipped('Intl supports formatting DateTime objects since 5.3.4');
            }
        }

        $constraint = $this->createConstraint(array('value' => $comparedValue));
        $constraint->message = 'Constraint Message';

        $this->validator->validate($dirtyValue, $constraint);

        $this->buildViolation('Constraint Message')
            ->setParameter('{{ value }}', $dirtyValueAsString)
            ->setParameter('{{ compared_value }}', $comparedValueString)
            ->setParameter('{{ compared_value_type }}', $comparedValueType)
            ->setCode($this->getErrorCode())
            ->assertRaised();
    }

    /**
     * @return array
     */
    public static function provideAllInvalidComparisons()
    {
        // The provider runs before setUp(), so we need to manually fix
        // the default timezone
        $timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $comparisons = self::addPhp5Dot5Comparisons(static::provideInvalidComparisons());

        date_default_timezone_set($timezone);

        return $comparisons;
    }

    /**
     * @return array
     */
    abstract public static function provideInvalidComparisons();

    /**
     * @param array|null $options Options for the constraint
     *
     * @return Constraint
     */
    abstract protected function createConstraint(?array $options = null);

    /**
     * @return string|null
     */
    protected function getErrorCode()
    {
    }
}
