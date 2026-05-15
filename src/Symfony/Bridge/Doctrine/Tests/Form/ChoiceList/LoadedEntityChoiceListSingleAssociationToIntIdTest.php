<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Form\ChoiceList;


use PHPUnit\Framework\Attributes\Group;
if (!class_exists('Symfony\Component\Form\Tests\Extension\Core\ChoiceList\AbstractChoiceListTest')) {
    return;
}
    #[Group('legacy')]
/**
 * @author Premi Giorgio <giosh94mhz@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LoadedEntityChoiceListSingleAssociationToIntIdTest extends AbstractEntityChoiceListSingleAssociationToIntIdTest
{
    /**
     * @return \Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    protected function createChoiceList()
    {
        $list = parent::createChoiceList();

        // load list
        $list->getChoices();

        return $list;
    }
}
