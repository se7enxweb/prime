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



use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
class TextTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\TextType';

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }

    #[Group('legacy')]    public function testLegacyName()
    {
        $form = $this->factory->create('text');

        $this->assertSame('text', $form->getConfig()->getType()->getName());
    }

    public function testSubmitNullReturnsNullWithEmptyDataAsString()
    {
        $form = $this->factory->create(static::TESTED_TYPE, 'name', array(
            'empty_data' => '',
        ));

        $form->submit(null);

        $this->assertNull($form->getData());
    }

    public static function provideZeros()
    {
        return array(
            array(0, '0'),
            array('0', '0'),
            array('00000', '00000'),
        );
    }

    #[DataProvider('provideZeros')]
    /**
     *
     * @see https://github.com/symfony/symfony/issues/1986
     */
    public function testSetDataThroughParamsWithZero($data, $dataAsString)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'data' => $data,
        ));
        $view = $form->createView();

        $this->assertFalse($form->isEmpty());

        $this->assertSame($dataAsString, $view->vars['value']);
        $this->assertSame($dataAsString, $form->getData());
    }
}
