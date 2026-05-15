<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;


use PHPUnit\Framework\Attributes\Group;
/**
 * @author Stepan Anchugov <kixxx1@gmail.com>
 */
class BirthdayTypeTest extends DateTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\BirthdayType';

    #[Group('legacy')]    public function testLegacyName()
    {
        $form = $this->factory->create('birthday');

        $this->assertSame('birthday', $form->getConfig()->getType()->getName());
    }

    /**
     */
    public function testSetInvalidYearsOption()
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);

        $this->factory->create(static::TESTED_TYPE, null, array(
            'years' => 'bad value',
        ));
    }
}
