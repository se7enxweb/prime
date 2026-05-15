<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\HttpKernelExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class HttpKernelExtensionTest extends TestCase
{
    /**
     */
    public function testFragmentWithError()
    {
        $this->expectException(\Twig\Error\RuntimeError::class);

        $renderer = $this->getFragmentHandler(fn() => throw new \Exception('foo'));

        $this->renderTemplate($renderer);
    }

    public function testRenderFragment()
    {
$renderer = $this->getFragmentHandler(fn() => new Response('html'));

        $response = $this->renderTemplate($renderer);

        $this->assertEquals('html', $response);
    }

    public function testUnknownFragmentRenderer()
    {
        $context = $this->getMockBuilder('Symfony\\Component\\HttpFoundation\\RequestStack')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $renderer = new FragmentHandler($context);

        if (method_exists($this, 'expectException')) {
            $this->expectException('InvalidArgumentException');
            $this->expectExceptionMessage('The "inline" renderer does not exist.');
        } else {
            $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('The "inline" renderer does not exist.');
        }

        $renderer->render('/foo');
    }

    protected function getFragmentHandler(callable $callback)
    {
        $strategy = $this->getMockBuilder('Symfony\\Component\\HttpKernel\\Fragment\\FragmentRendererInterface')->getMock();
        $strategy->expects($this->once())->method('getName')->willReturn('inline');
        $strategy->expects($this->once())->method('render')->willReturnCallback($callback);

        $context = $this->getMockBuilder('Symfony\\Component\\HttpFoundation\\RequestStack')
            ->disableOriginalConstructor()
            ->getMock()
        ;

$context->expects($this->any())->method('getCurrentRequest')->willReturn(Request::create('/'));

        return new FragmentHandler($context, array($strategy), false);
    }

    protected function renderTemplate(FragmentHandler $renderer, $template = '{{ render("foo") }}')
    {
$loader = new ArrayLoader(array('index' => $template));
$twig = new Environment($loader, array('debug' => true, 'cache' => false));
$twig->addExtension(new HttpKernelExtension($renderer));

        return $twig->render('index');
    }
}
