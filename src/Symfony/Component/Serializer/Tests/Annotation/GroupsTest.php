<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Annotation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class GroupsTest extends TestCase
{
    /**
     */
    public function testEmptyGroupsParameter()
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\InvalidArgumentException::class);

        new Groups(array('value' => array()));
    }

    /**
     */
    public function testNotAnArrayGroupsParameter()
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\InvalidArgumentException::class);

        new Groups(array('value' => 'coopTilleuls'));
    }

    /**
     */
    public function testInvalidGroupsParameter()
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\InvalidArgumentException::class);

        new Groups(array('value' => array('a', 1, new \stdClass())));
    }

    public function testGroupsParameters()
    {
        $validData = array('a', 'b');

        $groups = new Groups(array('value' => $validData));
        $this->assertEquals($validData, $groups->getGroups());
    }
}
