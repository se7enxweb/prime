<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Console\Descriptor;


use PHPUnit\Framework\Attributes\RequiresPhp;
use Symfony\Bundle\FrameworkBundle\Console\Descriptor\JsonDescriptor;
    #[RequiresPhp('5.4')]
/**
 */
class JsonDescriptorTest extends AbstractDescriptorTest
{
    protected function getDescriptor()
    {
        return new JsonDescriptor();
    }

    protected static function getFormat()
    {
        return 'json';
    }
}
