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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Translation\Loader\XliffFileLoader;

class XliffFileLoaderTest extends TestCase
{
    public function testLoad()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/resources.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
        $this->assertSame(array(), libxml_get_errors());
        $this->assertContainsOnly('string', $catalogue->all('domain1'));
    }

    public function testLoadWithInternalErrorsEnabled()
    {
        $internalErrors = libxml_use_internal_errors(true);

        $this->assertSame(array(), libxml_get_errors());

        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/resources.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
        $this->assertSame(array(), libxml_get_errors());

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
    }

    public function testLoadWithExternalEntitiesDisabled()
    {

        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/resources.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');


        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    public function testLoadWithResname()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resname.xlf', 'en', 'domain1');

        $this->assertEquals(array('foo' => 'bar', 'bar' => 'baz', 'baz' => 'foo', 'qux' => 'qux source'), $catalogue->all('domain1'));
    }

    public function testIncompleteResource()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resources.xlf', 'en', 'domain1');

        $this->assertEquals(array('foo' => 'bar', 'extra' => 'extra', 'key' => '', 'test' => 'with'), $catalogue->all('domain1'));
    }

    public function testEncoding()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/encoding.xlf', 'en', 'domain1');

        $this->assertEquals(mb_convert_encoding('föö', 'ISO-8859-1', 'UTF-8'), $catalogue->get('bar', 'domain1'));
        $this->assertEquals(mb_convert_encoding('bär', 'ISO-8859-1', 'UTF-8'), $catalogue->get('foo', 'domain1'));
        $this->assertEquals(array('notes' => array(array('content' => mb_convert_encoding('bäz', 'ISO-8859-1', 'UTF-8')))), $catalogue->getMetadata('foo', 'domain1'));
    }

    public function testTargetAttributesAreStoredCorrectly()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/with-attributes.xlf', 'en', 'domain1');

        $metadata = $catalogue->getMetadata('foo', 'domain1');
        $this->assertEquals('translated', $metadata['target-attributes']['state']);
    }

    /**
     */
    public function testLoadInvalidResource()
    {
        $this->expectException(\Symfony\Component\Translation\Exception\InvalidResourceException::class);

        $loader = new XliffFileLoader();
        $loader->load(__DIR__.'/../fixtures/resources.php', 'en', 'domain1');
    }

    /**
     */
    public function testLoadResourceDoesNotValidate()
    {
        $this->expectException(\Symfony\Component\Translation\Exception\InvalidResourceException::class);

        $loader = new XliffFileLoader();
        $loader->load(__DIR__.'/../fixtures/non-valid.xlf', 'en', 'domain1');
    }

    /**
     */
    public function testLoadNonExistingResource()
    {
        $this->expectException(\Symfony\Component\Translation\Exception\NotFoundResourceException::class);

        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/non-existing.xlf';
        $loader->load($resource, 'en', 'domain1');
    }

    /**
     */
    public function testLoadThrowsAnExceptionIfFileNotLocal()
    {
        $this->expectException(\Symfony\Component\Translation\Exception\InvalidResourceException::class);

        $loader = new XliffFileLoader();
        $resource = 'http://example.com/resources.xlf';
        $loader->load($resource, 'en', 'domain1');
    }

    /**
     */
    public function testDocTypeIsNotAllowed()
    {
        $this->expectException(\Symfony\Component\Translation\Exception\InvalidResourceException::class);
        $this->expectExceptionMessage('Document types are not allowed.');

        $loader = new XliffFileLoader();
        $loader->load(__DIR__.'/../fixtures/withdoctype.xlf', 'en', 'domain1');
    }

    public function testParseEmptyFile()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/empty.xlf';

        $this->expectException('Symfony\Component\Translation\Exception\InvalidResourceException');
        $this->expectExceptionMessage(sprintf('Unable to load "%s":', $resource));

        $loader->load($resource, 'en', 'domain1');
    }

    public function testLoadNotes()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/withnote.xlf', 'en', 'domain1');

        $this->assertEquals(array('notes' => array(array('priority' => 1, 'content' => 'foo'))), $catalogue->getMetadata('foo', 'domain1'));
        // message without target
        $this->assertEquals(array('notes' => array(array('content' => 'bar', 'from' => 'foo'))), $catalogue->getMetadata('extra', 'domain1'));
        // message with empty target
        $this->assertEquals(array('notes' => array(array('content' => 'baz'), array('priority' => 2, 'from' => 'bar', 'content' => 'qux'))), $catalogue->getMetadata('key', 'domain1'));
    }

    public function testLoadVersion2()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/resources-2.0.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
        $this->assertSame(array(), libxml_get_errors());

        $domains = $catalogue->all();
        $this->assertCount(3, $domains['domain1']);
        $this->assertContainsOnly('string', $catalogue->all('domain1'));

        // target attributes
        $this->assertEquals(array('target-attributes' => array('order' => 1)), $catalogue->getMetadata('bar', 'domain1'));
    }
}
