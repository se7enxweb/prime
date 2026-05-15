<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Descriptor;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

abstract class AbstractDescriptorTest extends TestCase
{    #[DataProvider('getDescribeInputArgumentTestData')]
    public function testDescribeInputArgument(InputArgument $argument, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $argument);
    }    #[DataProvider('getDescribeInputOptionTestData')]
    public function testDescribeInputOption(InputOption $option, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $option);
    }    #[DataProvider('getDescribeInputDefinitionTestData')]
    public function testDescribeInputDefinition(InputDefinition $definition, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $definition);
    }    #[DataProvider('getDescribeCommandTestData')]
    public function testDescribeCommand(Command $command, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $command);
    }    #[DataProvider('getDescribeApplicationTestData')]
    public function testDescribeApplication(Application $application, $expectedDescription)
    {
        // Replaces the dynamic placeholders of the command help text with a static version.
        // The placeholder %command.full_name% includes the script path that is not predictable
        // and can not be tested against.
        foreach ($application->all() as $command) {
            $command->setHelp(str_replace('%command.full_name%', 'app/console %command.name%', $command->getHelp()));
        }

        $this->assertDescription($expectedDescription, $application);
    }

    public static function getDescribeInputArgumentTestData()
    {
        return self::getDescriptionTestData(ObjectsProvider::getInputArguments());
    }

    public static function getDescribeInputOptionTestData()
    {
        return self::getDescriptionTestData(ObjectsProvider::getInputOptions());
    }

    public static function getDescribeInputDefinitionTestData()
    {
        return self::getDescriptionTestData(ObjectsProvider::getInputDefinitions());
    }

    public static function getDescribeCommandTestData()
    {
        return self::getDescriptionTestData(ObjectsProvider::getCommands());
    }

    public static function getDescribeApplicationTestData()
    {
        return self::getDescriptionTestData(ObjectsProvider::getApplications());
    }

    abstract protected function getDescriptor();

    abstract protected static function getFormat();

    protected static function getDescriptionTestData(array $objects)
    {
        $data = array();
        foreach ($objects as $name => $object) {
            $description = file_get_contents(sprintf('%s/../Fixtures/%s.%s', __DIR__, $name, static::getFormat()));
            $data[] = array($object, $description);
        }

        return $data;
    }

    protected function assertDescription($expectedDescription, $describedObject)
    {
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $this->getDescriptor()->describe($output, $describedObject, array('raw_output' => true));
        $this->assertEquals(trim($expectedDescription), trim(str_replace(PHP_EOL, "\n", $output->fetch())));
    }
}
