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


use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

abstract class AbstractDescriptorTest extends TestCase
{    #[DataProvider('getDescribeRouteCollectionTestData')]
    public function testDescribeRouteCollection(RouteCollection $routes, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $routes);
    }

    public static function getDescribeRouteCollectionTestData()
    {
        return self::getDescriptionTestData(ObjectsProvider::getRouteCollections());
    }
    #[DataProvider('getDescribeRouteTestData')]
    public function testDescribeRoute(Route $route, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $route);
    }

    public static function getDescribeRouteTestData()
    {
        return self::getDescriptionTestData(ObjectsProvider::getRoutes());
    }
    #[DataProvider('getDescribeContainerParametersTestData')]
    public function testDescribeContainerParameters(ParameterBag $parameters, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $parameters);
    }

    public static function getDescribeContainerParametersTestData()
    {
        return self::getDescriptionTestData(ObjectsProvider::getContainerParameters());
    }
    #[DataProvider('getDescribeContainerBuilderTestData')]
    public function testDescribeContainerBuilder(ContainerBuilder $builder, $expectedDescription, array $options)
    {
        $this->assertDescription($expectedDescription, $builder, $options);
    }

    public static function getDescribeContainerBuilderTestData()
    {
        return self::getContainerBuilderDescriptionTestData(ObjectsProvider::getContainerBuilders());
    }
    #[DataProvider('provideLegacySynchronizedServiceDefinitionTestData')]
    #[Group('legacy')]
    /**
     */
    public function testLegacyDescribeSynchronizedServiceDefinition(Definition $definition, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $definition);
    }
    #[Group('legacy')]
    /**
     */
    public static function provideLegacySynchronizedServiceDefinitionTestData()
    {
        return self::getDescriptionTestData(ObjectsProvider::getLegacyContainerDefinitions());
    }
    #[DataProvider('getDescribeContainerDefinitionTestData')]
    public function testDescribeContainerDefinition(Definition $definition, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $definition);
    }

    public static function getDescribeContainerDefinitionTestData()
    {
        return self::getDescriptionTestData(ObjectsProvider::getContainerDefinitions());
    }
    #[DataProvider('getDescribeContainerAliasTestData')]
    public function testDescribeContainerAlias(Alias $alias, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $alias);
    }

    public static function getDescribeContainerAliasTestData()
    {
        return self::getDescriptionTestData(ObjectsProvider::getContainerAliases());
    }
    #[DataProvider('getDescribeContainerParameterTestData')]
    public function testDescribeContainerParameter($parameter, $expectedDescription, array $options)
    {
        $this->assertDescription($expectedDescription, $parameter, $options);
    }

    public static function getDescribeContainerParameterTestData()
    {
        $data = self::getDescriptionTestData(ObjectsProvider::getContainerParameter());

        $data[0][] = array('parameter' => 'database_name');
        $data[1][] = array('parameter' => 'twig.form.resources');

        return $data;
    }
    #[DataProvider('getDescribeEventDispatcherTestData')]
    public function testDescribeEventDispatcher(EventDispatcher $eventDispatcher, $expectedDescription, array $options)
    {
        $this->assertDescription($expectedDescription, $eventDispatcher, $options);
    }

    public static function getDescribeEventDispatcherTestData()
    {
        return self::getEventDispatcherDescriptionTestData(ObjectsProvider::getEventDispatchers());
    }
    #[DataProvider('getDescribeCallableTestData')]
    public function testDescribeCallable($callable, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $callable);
    }

    public static function getDescribeCallableTestData()
    {
        return self::getDescriptionTestData(ObjectsProvider::getCallables());
    }

    abstract protected function getDescriptor();

    abstract protected static function getFormat();

    private function assertDescription($expectedDescription, $describedObject, array $options = array())
    {
        $options['raw_output'] = true;
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);

        if ('txt' === static::getFormat()) {
            $options['output'] = new SymfonyStyle(new ArrayInput(array()), $output);
        }

        $this->getDescriptor()->describe($output, $describedObject, $options);

        if ('json' === static::getFormat()) {
            $this->assertEquals(json_decode($expectedDescription), json_decode($output->fetch()));
        } else {
            $this->assertEquals(trim($expectedDescription), trim(str_replace(PHP_EOL, "\n", $output->fetch())));
        }
    }

    private static function getDescriptionTestData(array $objects)
    {
        $data = array();
        foreach ($objects as $name => $object) {
            $description = file_get_contents(sprintf('%s/../../Fixtures/Descriptor/%s.%s', __DIR__, $name, static::getFormat()));
            $data[] = array($object, $description);
        }

        return $data;
    }

    private static function getContainerBuilderDescriptionTestData(array $objects)
    {
        $variations = array(
            'services' => array('show_private' => true),
            'public' => array('show_private' => false),
            'tag1' => array('show_private' => true, 'tag' => 'tag1'),
            'tags' => array('group_by' => 'tags', 'show_private' => true),
        );

        $data = array();
        foreach ($objects as $name => $object) {
            foreach ($variations as $suffix => $options) {
                $description = file_get_contents(sprintf('%s/../../Fixtures/Descriptor/%s_%s.%s', __DIR__, $name, $suffix, static::getFormat()));
                $data[] = array($object, $description, $options);
            }
        }

        return $data;
    }

    private static function getEventDispatcherDescriptionTestData(array $objects)
    {
        $variations = array(
            'events' => array(),
            'event1' => array('event' => 'event1'),
        );

        $data = array();
        foreach ($objects as $name => $object) {
            foreach ($variations as $suffix => $options) {
                $description = file_get_contents(sprintf('%s/../../Fixtures/Descriptor/%s_%s.%s', __DIR__, $name, $suffix, static::getFormat()));
                $data[] = array($object, $description, $options);
            }
        }

        return $data;
    }
}
