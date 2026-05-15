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
use Symfony\Component\Form\ChoiceList\View\ChoiceView;

class TimezoneTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\TimezoneType';

    #[Group('legacy')]    public function testLegacyName()
    {
        $form = $this->factory->create('timezone');

        $this->assertSame('timezone', $form->getConfig()->getType()->getName());
    }

    public function testTimezonesAreSelectable()
    {
        $choices = $this->factory->create(static::TESTED_TYPE)
            ->createView()->vars['choices'];

        $this->assertArrayHasKey('Africa', $choices);
        $this->assertContainsEquals(new ChoiceView('Africa/Kinshasa', 'Africa/Kinshasa', 'Kinshasa'), $choices['Africa']);

        $this->assertArrayHasKey('America', $choices);
        $this->assertContainsEquals(new ChoiceView('America/New_York', 'America/New_York', 'New York'), $choices['America']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'Africa/Kinshasa', $expectedData = 'Africa/Kinshasa')
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'empty_data' => $emptyData,
        ));
        $form->submit(null);

        $this->assertSame($emptyData, $form->getViewData());
        $this->assertSame($expectedData, $form->getNormData());
        $this->assertSame($expectedData, $form->getData());
    }
}
