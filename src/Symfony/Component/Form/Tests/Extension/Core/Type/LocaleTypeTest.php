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
use Symfony\Component\Intl\Util\IntlTestHelper;

class LocaleTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\LocaleType';

    protected function setUp(): void
    {
        IntlTestHelper::requireIntl($this, false);

        parent::setUp();
    }

    #[Group('legacy')]    public function testLegacyName()
    {
        $form = $this->factory->create('locale');

        $this->assertSame('locale', $form->getConfig()->getType()->getName());
    }

    public function testLocalesAreSelectable()
    {
        $choices = $this->factory->create(static::TESTED_TYPE)
            ->createView()->vars['choices'];

        $this->assertContainsEquals(new ChoiceView('en', 'en', 'English'), $choices);
        $this->assertContainsEquals(new ChoiceView('en_GB', 'en_GB', 'English (United Kingdom)'), $choices);
        $this->assertContainsEquals(new ChoiceView('zh_Hant_MO', 'zh_Hant_MO', 'Chinese (Traditional, Macau SAR China)'), $choices);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'en', $expectedData = 'en')
    {
        parent::testSubmitNullUsesDefaultEmptyData($emptyData, $expectedData);
    }
}
