<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;


use PHPUnit\Framework\Attributes\DataProvider;
class ProfilerTest extends WebTestCase
{
    #[DataProvider('getConfigs')]    public function testProfilerIsDisabled($insulate)
    {
        $client = $this->createClient(array('test_case' => 'Profiler', 'root_config' => 'config.yml'));
        if ($insulate) {
            $client->insulate();
        }

        $client->request('GET', '/profiler');
        $this->assertFalse($client->getProfile());

        // enable the profiler for the next request
        $client->enableProfiler();
        $crawler = $client->request('GET', '/profiler');
        $profile = $client->getProfile();
        $this->assertIsObject($profile);

        $client->request('GET', '/profiler');
        $this->assertFalse($client->getProfile());
    }

    public static function getConfigs()
    {
        return array(
            array(false),
            array(true),
        );
    }
}
