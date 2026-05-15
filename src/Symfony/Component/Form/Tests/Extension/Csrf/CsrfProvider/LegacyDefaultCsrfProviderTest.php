<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Csrf\CsrfProvider;


use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\DefaultCsrfProvider;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
#[Group('legacy')]
/**
 */
class LegacyDefaultCsrfProviderTest extends TestCase
{
    protected $provider;

    protected function setUp(): void
    {
        $this->provider = new DefaultCsrfProvider('SECRET');
    }

    protected function tearDown(): void
    {
        $this->provider = null;
    }

    public function testGenerateCsrfToken()
    {
        session_start();

        $token = $this->provider->generateCsrfToken('foo');

        $this->assertEquals(sha1('SECRET'.'foo'.session_id()), $token);
    }

    #[RequiresPhp('5.4')]
    /**
     */
    public function testGenerateCsrfTokenOnUnstartedSession()
    {
        session_id('touti');

        $this->assertSame(PHP_SESSION_NONE, session_status());

        $token = $this->provider->generateCsrfToken('foo');

        $this->assertEquals(sha1('SECRET'.'foo'.session_id()), $token);
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    public function testIsCsrfTokenValidSucceeds()
    {
        session_start();

        $token = sha1('SECRET'.'foo'.session_id());

        $this->assertTrue($this->provider->isCsrfTokenValid('foo', $token));
    }

    public function testIsCsrfTokenValidFails()
    {
        session_start();

        $token = sha1('SECRET'.'bar'.session_id());

        $this->assertFalse($this->provider->isCsrfTokenValid('foo', $token));
    }
}
