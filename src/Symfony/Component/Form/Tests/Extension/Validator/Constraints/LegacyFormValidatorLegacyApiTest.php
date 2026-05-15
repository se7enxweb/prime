<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\Constraints;


use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\Validation;

#[Group('legacy')]
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LegacyFormValidatorLegacyApiTest extends FormValidatorTest
{
    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_5_BC;
    }
}
