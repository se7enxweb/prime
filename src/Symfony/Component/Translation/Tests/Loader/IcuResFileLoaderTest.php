<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Loader;


use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RequiresPhp;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Translation\Loader\IcuResFileLoader;

#[RequiresPhpExtension('intl')]
/**
 */
class IcuResFileLoaderTest extends LocalizedTestCase
{
    public function testLoad()
    {
        // resource is build using genrb command
        $loader = new IcuResFileLoader();
        $resource = __DIR__.'/../fixtures/resourcebundle/res';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(array('foo' => 'bar'), $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new DirectoryResource($resource)), $catalogue->getResources());
    }

    /**
     */
    public function testLoadNonExistingResource()
    {
        $this->expectException(\Symfony\Component\Translation\Exception\NotFoundResourceException::class);

        $loader = new IcuResFileLoader();
        $loader->load(__DIR__.'/../fixtures/non-existing.txt', 'en', 'domain1');
    }

    /**
     */
    public function testLoadInvalidResource()
    {
        $this->expectException(\Symfony\Component\Translation\Exception\InvalidResourceException::class);

        $loader = new IcuResFileLoader();
        $loader->load(__DIR__.'/../fixtures/resourcebundle/corrupted', 'en', 'domain1');
    }
}
