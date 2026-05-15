<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Resources;


use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TranslationFilesTest extends TestCase
{
    #[DataProvider('provideTranslationFiles')]    public function testTranslationFileIsValid($filePath)
    {
        $doc = new \DOMDocument();
            $this->assertTrue($doc->load($filePath), sprintf('"%s" is not a valid XML file.', $filePath));

        $this->addToAssertionCount(1);
    }

    public static function provideTranslationFiles()
    {
        return array_map(
            function ($filePath) { return (array) $filePath; },
            glob(\dirname(\dirname(__DIR__)).'/Resources/translations/*.xlf')
        );
    }

    public function testNorwegianAlias()
    {
        $this->assertFileEquals(
            \dirname(\dirname(__DIR__)).'/Resources/translations/validators.nb.xlf',
            \dirname(\dirname(__DIR__)).'/Resources/translations/validators.no.xlf',
            'The NO locale should be an alias for the NB variant of the Norwegian language.'
        );
    }
}
