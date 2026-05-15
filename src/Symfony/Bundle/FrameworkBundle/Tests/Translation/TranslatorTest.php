<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Translation;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageSelector;

class TranslatorTest extends TestCase
{
    protected $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/sf2_translation';
        $this->deleteTmpDir();
    }

    protected function tearDown(): void
    {
        $this->deleteTmpDir();
    }

    protected function deleteTmpDir()
    {
        if (!file_exists($dir = $this->tmpDir)) {
            return;
        }

        $fs = new Filesystem();
        $fs->remove($dir);
    }

    public function testTransWithoutCaching()
    {
        $translator = $this->getTranslator($this->getLoader());
        $translator->setLocale('fr');
        $translator->setFallbackLocales(array('en', 'es', 'pt-PT', 'pt_BR', 'fr.UTF-8', 'sr@latin'));

        $this->assertEquals('foo (FR)', $translator->trans('foo'));
        $this->assertEquals('bar (EN)', $translator->trans('bar'));
        $this->assertEquals('foobar (ES)', $translator->trans('foobar'));
        $this->assertEquals('choice 0 (EN)', $translator->transChoice('choice', 0));
        $this->assertEquals('no translation', $translator->trans('no translation'));
        $this->assertEquals('foobarfoo (PT-PT)', $translator->trans('foobarfoo'));
        $this->assertEquals('other choice 1 (PT-BR)', $translator->transChoice('other choice', 1));
        $this->assertEquals('foobarbaz (fr.UTF-8)', $translator->trans('foobarbaz'));
        $this->assertEquals('foobarbax (sr@latin)', $translator->trans('foobarbax'));
    }

    public function testTransWithCaching()
    {
        // prime the cache
        $translator = $this->getTranslator($this->getLoader(), array('cache_dir' => $this->tmpDir));
        $translator->setLocale('fr');
        $translator->setFallbackLocales(array('en', 'es', 'pt-PT', 'pt_BR', 'fr.UTF-8', 'sr@latin'));

        $this->assertEquals('foo (FR)', $translator->trans('foo'));
        $this->assertEquals('bar (EN)', $translator->trans('bar'));
        $this->assertEquals('foobar (ES)', $translator->trans('foobar'));
        $this->assertEquals('choice 0 (EN)', $translator->transChoice('choice', 0));
        $this->assertEquals('no translation', $translator->trans('no translation'));
        $this->assertEquals('foobarfoo (PT-PT)', $translator->trans('foobarfoo'));
        $this->assertEquals('other choice 1 (PT-BR)', $translator->transChoice('other choice', 1));
        $this->assertEquals('foobarbaz (fr.UTF-8)', $translator->trans('foobarbaz'));
        $this->assertEquals('foobarbax (sr@latin)', $translator->trans('foobarbax'));

        // do it another time as the cache is primed now
        $loader = $this->getMockBuilder('Symfony\Component\Translation\Loader\LoaderInterface')->getMock();
        $loader->expects($this->never())->method('load');

        $translator = $this->getTranslator($loader, array('cache_dir' => $this->tmpDir));
        $translator->setLocale('fr');
        $translator->setFallbackLocales(array('en', 'es', 'pt-PT', 'pt_BR', 'fr.UTF-8', 'sr@latin'));

        $this->assertEquals('foo (FR)', $translator->trans('foo'));
        $this->assertEquals('bar (EN)', $translator->trans('bar'));
        $this->assertEquals('foobar (ES)', $translator->trans('foobar'));
        $this->assertEquals('choice 0 (EN)', $translator->transChoice('choice', 0));
        $this->assertEquals('no translation', $translator->trans('no translation'));
        $this->assertEquals('foobarfoo (PT-PT)', $translator->trans('foobarfoo'));
        $this->assertEquals('other choice 1 (PT-BR)', $translator->transChoice('other choice', 1));
        $this->assertEquals('foobarbaz (fr.UTF-8)', $translator->trans('foobarbaz'));
        $this->assertEquals('foobarbax (sr@latin)', $translator->trans('foobarbax'));
    }

    public function testTransWithCachingWithInvalidLocale()
    {
        $loader = $this->getMockBuilder('Symfony\Component\Translation\Loader\LoaderInterface')->getMock();
        $translator = $this->getTranslator($loader, array('cache_dir' => $this->tmpDir), 'loader', '\Symfony\Bundle\FrameworkBundle\Tests\Translation\TranslatorWithInvalidLocale');
        $translator->setLocale('invalid locale');

        $this->expectException('\InvalidArgumentException');
        $translator->trans('foo');
    }

    public function testLoadResourcesWithoutCaching()
    {
        $loader = new \Symfony\Component\Translation\Loader\YamlFileLoader();
        $resourceFiles = array(
            'fr' => array(
                __DIR__.'/../Fixtures/Resources/translations/messages.fr.yml',
            ),
        );

        $translator = $this->getTranslator($loader, array('resource_files' => $resourceFiles), 'yml');
        $translator->setLocale('fr');

        $this->assertEquals('répertoire', $translator->trans('folder'));
    }

    public function testGetDefaultLocale()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container
            ->expects($this->once())
            ->method('getParameter')
            ->with('kernel.default_locale')
            ->willReturn('en')
        ;

$translator = new Translator($container, new MessageSelector());

$this->assertSame('en', $translator->getLocale());
    }    #[DataProvider('getDebugModeAndCacheDirCombinations')]
    public function testResourceFilesOptionLoadsBeforeOtherAddedResources($debug, $enableCache)
    {
$someCatalogue = $this->getCatalogue('some_locale', array());

        $loader = $this->getMockBuilder('Symfony\Component\Translation\Loader\LoaderInterface')->getMock();

        $loader->expects($this->any())
            ->method('load')
            ->willReturnCallback(function ($resource, $locale, $domain) use ($someCatalogue) {
                return $someCatalogue;
            });

        $options = array(
            'resource_files' => array('some_locale' => array('messages.some_locale.loader')),
            'debug' => $debug,
        );

        if ($enableCache) {
            $options['cache_dir'] = $this->tmpDir;
        }

        /** @var Translator $translator */
        $translator = $this->createTranslator($loader, $options);
        $translator->addResource('loader', 'second_resource.some_locale.loader', 'some_locale', 'messages');

        $translator->trans('some_message', array(), null, 'some_locale');
        $this->addToAssertionCount(1);
    }

    public static function getDebugModeAndCacheDirCombinations()
    {
        return array(
            array(false, false),
            array(true, false),
            array(false, true),
            array(true, true),
        );
    }

    protected function getCatalogue($locale, $messages, $resources = array())
    {
        $catalogue = new MessageCatalogue($locale);
        foreach ($messages as $key => $translation) {
            $catalogue->set($key, $translation);
        }
        foreach ($resources as $resource) {
            $catalogue->addResource($resource);
        }

        return $catalogue;
    }

    protected function getLoader()
    {
        $catalogues = array(
            'fr' => $this->getCatalogue('fr', array('foo' => 'foo (FR)')),
            'en' => $this->getCatalogue('en', array(
                'foo' => 'foo (EN)',
                'bar' => 'bar (EN)',
                'choice' => '{0} choice 0 (EN)|{1} choice 1 (EN)|]1,Inf] choice inf (EN)',
            )),
            'es' => $this->getCatalogue('es', array('foobar' => 'foobar (ES)')),
            'pt-PT' => $this->getCatalogue('pt-PT', array('foobarfoo' => 'foobarfoo (PT-PT)')),
            'pt_BR' => $this->getCatalogue('pt_BR', array(
                'other choice' => '{0} other choice 0 (PT-BR)|{1} other choice 1 (PT-BR)|]1,Inf] other choice inf (PT-BR)',
            )),
            'fr.UTF-8' => $this->getCatalogue('fr.UTF-8', array('foobarbaz' => 'foobarbaz (fr.UTF-8)')),
            'sr@latin' => $this->getCatalogue('sr@latin', array('foobarbax' => 'foobarbax (sr@latin)')),
        );

        $loader = $this->getMockBuilder('Symfony\Component\Translation\Loader\LoaderInterface')->getMock();
        $loader
            ->expects($this->any())
            ->method('load')
            ->willReturnCallback(function ($resource, $locale, $domain) use ($catalogues) {
                return isset($catalogues[$locale]) ? $catalogues[$locale] : new \Symfony\Component\Translation\MessageCatalogue($locale);
            })
        ;

        return $loader;
    }

    protected function getContainer($loader)
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container
            ->expects($this->any())
            ->method('get')
            ->willReturn($loader)
        ;

        return $container;
    }

    public function getTranslator($loader, $options = array(), $loaderFomat = 'loader', $translatorClass = '\Symfony\Bundle\FrameworkBundle\Translation\Translator')
    {
        $translator = $this->createTranslator($loader, $options, $translatorClass, $loaderFomat);

        if ('loader' === $loaderFomat) {
            $translator->addResource('loader', 'foo', 'fr');
            $translator->addResource('loader', 'foo', 'en');
            $translator->addResource('loader', 'foo', 'es');
            $translator->addResource('loader', 'foo', 'pt-PT'); // European Portuguese
            $translator->addResource('loader', 'foo', 'pt_BR'); // Brazilian Portuguese
            $translator->addResource('loader', 'foo', 'fr.UTF-8');
            $translator->addResource('loader', 'foo', 'sr@latin'); // Latin Serbian
        }

        return $translator;
    }

    public function testWarmup()
    {
        $loader = new \Symfony\Component\Translation\Loader\YamlFileLoader();
        $resourceFiles = array(
            'fr' => array(
                __DIR__.'/../Fixtures/Resources/translations/messages.fr.yml',
            ),
        );

        // prime the cache
        $translator = $this->getTranslator($loader, array('cache_dir' => $this->tmpDir, 'resource_files' => $resourceFiles), 'yml');
        $translator->setFallbackLocales(array('fr'));
        $translator->warmup($this->tmpDir);

        $loader = $this->getMockBuilder('Symfony\Component\Translation\Loader\LoaderInterface')->getMock();
        $loader
            ->expects($this->never())
            ->method('load');

        $translator = $this->getTranslator($loader, array('cache_dir' => $this->tmpDir, 'resource_files' => $resourceFiles), 'yml');
        $translator->setLocale('fr');
        $translator->setFallbackLocales(array('fr'));
        $this->assertEquals('répertoire', $translator->trans('folder'));
    }

    private function createTranslator($loader, $options, $translatorClass = '\Symfony\Bundle\FrameworkBundle\Translation\Translator', $loaderFomat = 'loader')
    {
        return new $translatorClass(
            $this->getContainer($loader),
            new MessageSelector(),
            array($loaderFomat => array($loaderFomat)),
            $options
        );
    }
}

class TranslatorWithInvalidLocale extends Translator
{
    /**
     * {@inheritdoc}
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }
}
