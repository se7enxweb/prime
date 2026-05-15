<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;



use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MemcacheSessionHandler;

#[RequiresPhpExtension('memcache')]
/**
 */
class MemcacheSessionHandlerTest extends TestCase
{
    const PREFIX = 'prefix_';
    const TTL = 1000;

    /**
     * @var MemcacheSessionHandler
     */
    protected $storage;

    protected $memcache;

    protected function setUp(): void
    {
        if (\defined('HHVM_VERSION')) {
            $this->markTestSkipped('PHPUnit_MockObject cannot mock the Memcache class on HHVM. See https://github.com/sebastianbergmann/phpunit-mock-objects/pull/289');
        }

        parent::setUp();
        $this->memcache = $this->getMockBuilder(MemcacheMock::class)->getMock();
        $this->storage = new MemcacheSessionHandler(
            $this->memcache,
            array('prefix' => self::PREFIX, 'expiretime' => self::TTL)
        );
    }

    protected function tearDown(): void
    {
        $this->memcache = null;
        $this->storage = null;
        parent::tearDown();
    }

    public function testOpenSession()
    {
        $this->assertTrue($this->storage->open('', ''));
    }

    public function testCloseSession()
    {
        $this->assertTrue($this->storage->close());
    }

    public function testReadSession()
    {
        $this->memcache
            ->expects($this->once())
            ->method('get')
            ->with(self::PREFIX.'id')
        ;

        $this->assertEquals('', $this->storage->read('id'));
    }

    public function testWriteSession()
    {
        $this->memcache
            ->expects($this->once())
            ->method('set')
            ->with(self::PREFIX.'id', 'data', 0, $this->equalTo(time() + self::TTL, 2))
            ->willReturn(true)
        ;

$this->assertTrue($this->storage->write('id', 'data'));
    }

    public function testDestroySession()
    {
        $this->memcache
            ->expects($this->once())
            ->method('delete')
            ->with(self::PREFIX.'id')
            ->willReturn(true)
        ;

$this->assertTrue($this->storage->destroy('id'));
    }

    public function testGcSession()
    {
$this->assertNotFalse($this->storage->gc(123));
    }

    #[DataProvider('getOptionFixtures')]    public function testSupportedOptions($options, $supported)
    {
        try {
            new MemcacheSessionHandler($this->memcache, $options);
            $this->assertTrue($supported);
        } catch (\InvalidArgumentException $e) {
            $this->assertFalse($supported);
        }
    }

    public static function getOptionFixtures()
    {
        return array(
            array(array('prefix' => 'session'), true),
            array(array('expiretime' => 100), true),
            array(array('prefix' => 'session', 'expiretime' => 200), true),
            array(array('expiretime' => 100, 'foo' => 'bar'), false),
        );
    }

    public function testGetConnection()
    {
        $method = new \ReflectionMethod($this->storage, 'getMemcache');

        $this->assertInstanceOf('\Memcache', $method->invoke($this->storage));
    }
}

class MemcacheMock extends \Memcache
{
    public function setserverparams(string $host, ?int $tcp_port = null, ?float $timeout = null, ?int $retry_interval = null, ?bool $status = null, $failure_callback = null): bool { return true; }
    public function getserverstatus(string $host, ?int $tcp_port = null): int|bool { return false; }
    public function add(array|string $key, mixed $value = null, ?int $flags = null, ?int $exptime = null, ?int $cas = null): bool { return false; }
    public function set(array|string $key, mixed $value = null, ?int $flags = null, ?int $exptime = null, ?int $cas = null): bool { return false; }
    public function replace(array|string $key, mixed $value = null, ?int $flags = null, ?int $exptime = null, ?int $cas = null): bool { return false; }
    public function cas(array|string $key, mixed $value = null, ?int $flags = null, ?int $exptime = null, ?int $cas = null): bool { return false; }
    public function append(array|string $key, mixed $value = null, ?int $flags = null, ?int $exptime = null, ?int $cas = null): bool { return false; }
    public function prepend(array|string $key, mixed $value = null, ?int $flags = null, ?int $exptime = null, ?int $cas = null): bool { return false; }
    public function delete(array|string $key, ?int $exptime = null): array|bool { return false; }
    public function getstats(?string $type = null, ?int $slabid = null, ?int $limit = null): array|bool { return false; }
    public function getextendedstats(?string $type = null, ?int $slabid = null, ?int $limit = null): array|bool { return false; }
    public function setcompressthreshold(int $threshold, ?float $min_savings = null): bool { return false; }
    public function increment(array|string $key, ?int $value = null, ?int $defval = null, ?int $exptime = null): array|int|bool { return false; }
    public function decrement(array|string $key, ?int $value = null, ?int $defval = null, ?int $exptime = null): array|int|bool { return false; }
    public function flush(?int $delay = null): bool { return false; }
}
