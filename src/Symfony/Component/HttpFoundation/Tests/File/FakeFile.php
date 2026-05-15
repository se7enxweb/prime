<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\File;

use Symfony\Component\HttpFoundation\File\File as OrigFile;

class FakeFile extends OrigFile
{
    private $realpath;

    public function __construct($realpath, $path)
    {
        $this->realpath = $realpath;
        parent::__construct($path, false);
    }

    #[\ReturnTypeWillChange]
    public function isReadable()
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function getRealpath()
    {
        return $this->realpath;
    }

    #[\ReturnTypeWillChange]
    public function getSize()
    {
        return 42;
    }

    #[\ReturnTypeWillChange]
    public function getMTime()
    {
        return time();
    }
}
