<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Kernel;


use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class MicroKernelTraitTest extends TestCase
{
    private $previousExceptionHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousExceptionHandler = set_exception_handler(function () {});
        restore_exception_handler();
    }

    protected function tearDown(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $current = set_exception_handler(null);
            restore_exception_handler();
            if ($current === $this->previousExceptionHandler) {
                break;
            }
            restore_exception_handler();
        }
        parent::tearDown();
    }
    #[RequiresPhp('5.4')]
    /**
     */
    public function test()
    {
        $kernel = new ConcreteMicroKernel('test', true);
        $kernel->boot();

        $request = Request::create('/');
        $response = $kernel->handle($request);

        $this->assertEquals('halloween', $response->getContent());
        $this->assertEquals('Have a great day!', $kernel->getContainer()->getParameter('halloween'));
        $this->assertInstanceOf('stdClass', $kernel->getContainer()->get('halloween'));
    }
}
