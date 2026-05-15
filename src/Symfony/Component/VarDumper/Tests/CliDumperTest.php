<?php

/*
 * (c) 2004-2026 7x (se7enxweb) <info@se7enx.com>. All rights reserved.
 *
 * This file is part of the 7x Prime package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests;



use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RequiresFunction;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Test\VarDumperTestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CliDumperTest extends VarDumperTestCase
{
    public function testGet()
    {
        require __DIR__.'/Fixtures/dumb-var.php';

        $dumper = new CliDumper('php://output');
        $dumper->setColors(false);
        $cloner = new VarCloner();
        $cloner->addCasters(array(
            ':stream' => function ($res, $a) {
                unset($a['uri'], $a['wrapper_data'], $a['timed_out'], $a['blocked'], $a['eof']);

                return $a;
            },
        ));
        $data = $cloner->cloneVar($var);

        ob_start();
        $dumper->dump($data);
        $out = ob_get_clean();
        $out = preg_replace('/[ \t]+$/m', '', $out);
        $intMax = PHP_INT_MAX;
        $res = (int) $var['res'];
        $closure54 = '';
        $r = \defined('HHVM_VERSION') ? '' : '#%d';

        if (\PHP_VERSION_ID >= 50400) {
            $closure54 = <<<EOTXT

    class: "Symfony\Component\VarDumper\Tests\CliDumperTest"
    this: Symfony\Component\VarDumper\Tests\CliDumperTest {{$r} …}
EOTXT;
        }

        $this->assertStringMatchesFormat(
            <<<EOTXT
array:24 [
  "number" => 1
  0 => &1 null
  "const" => 1.1
  1 => true
  2 => false
  3 => NAN
  4 => INF
  5 => -INF
  6 => {$intMax}
  "str" => "déjà\\n"
  7 => b"é\\x00"
  "[]" => []
  "res" => stream resource {@{$res}
%A  wrapper_type: "plainfile"
    stream_type: "STDIO"
    mode: "r"
    unread_bytes: 0
    seekable: true
%A  options: []
  }
  "obj" => Symfony\Component\VarDumper\Tests\Fixture\DumbFoo {#%d
    +foo: "foo"
    +bar: "bar"
  }
  "closure" => Closure {{$r}{$closure54}
    parameters: {
      \$a: {}
      &\$b: {
        typeHint: "PDO"
        default: null
      }
    }
    file: "{$var['file']}"
    line: "{$var['line']} to {$var['line']}"
  }
  "line" => {$var['line']}
  "nobj" => array:1 [
    0 => &3 {#%d}
  ]
  "recurs" => &4 array:1 [
    0 => &4 array:1 [&4]
  ]
  8 => &1 null
  "sobj" => Symfony\Component\VarDumper\Tests\Fixture\DumbFoo {#%d}
  "snobj" => &3 {#%d}
  "snobj2" => {#%d}
  "file" => "{$var['file']}"
  b"bin-key-é" => ""
]

EOTXT
            ,
            $out
        );
    }

    #[RequiresPhpExtension('xml')]    public function testXmlResource()
    {
        $var = xml_parser_create();
      $dump = $this->getDump($var);

      $this->assertTrue(false !== strpos($dump, 'xml resource {') || false !== strpos($dump, 'XMLParser {'));
    }

    public function testJsonCast()
    {
        $var = (array) json_decode('{"0":{},"1":null}');
        foreach ($var as &$v) {
        }
        $var[] = &$v;
        $var[''] = 2;

        if (\PHP_VERSION_ID >= 70200) {
            $this->assertDumpMatchesFormat(
                <<<'EOTXT'
array:4 [
  0 => {}
  1 => &1 null
  2 => &1 null
  "" => 2
]
EOTXT
                ,
                $var
            );
        } else {
            $this->assertDumpMatchesFormat(
                <<<'EOTXT'
array:4 [
  "0" => {}
  "1" => &1 null
  0 => &1 null
  "" => 2
]
EOTXT
                ,
                $var
            );
        }
    }

    public function testObjectCast()
    {
        $var = (object) array(1 => 1);
        $var->{1} = 2;

        if (\PHP_VERSION_ID >= 70200) {
            $this->assertDumpMatchesFormat(
                <<<'EOTXT'
{
  +"1": 2
}
EOTXT
                ,
                $var
            );
        } else {
            $this->assertDumpMatchesFormat(
                <<<'EOTXT'
{
  +1: 1
  +"1": 2
}
EOTXT
                ,
                $var
            );
        }
    }

    public function testClosedResource()
    {
        if (\defined('HHVM_VERSION') && HHVM_VERSION_ID < 30600) {
            $this->markTestSkipped();
        }

        $var = fopen(__FILE__, 'r');
        fclose($var);

        $dumper = new CliDumper('php://output');
        $dumper->setColors(false);
        $cloner = new VarCloner();
        $data = $cloner->cloneVar($var);

        ob_start();
        $dumper->dump($data);
        $out = ob_get_clean();
        $res = (int) $var;

        $this->assertStringMatchesFormat(
            <<<EOTXT
Closed resource @{$res}

EOTXT
            ,
            $out
        );
    }

    #[RequiresFunction('Twig\Template::getSourceContext')]    public function testThrowingCaster()
    {
        $out = fopen('php://memory', 'r+b');

        require_once __DIR__.'/Fixtures/Twig.php';
        $twig = new \__TwigTemplate_VarDumperFixture_u75a09(new Environment(new FilesystemLoader()));

        $dumper = new CliDumper();
        $dumper->setColors(false);
        $cloner = new VarCloner();
        $cloner->addCasters(array(
            ':stream' => function ($res, $a) {
                unset($a['wrapper_data']);

                return $a;
            },
        ));
        $cloner->addCasters(array(
            ':stream' => eval('return function () use ($twig) {
                try {
                    $twig->render(array());
                } catch (\Twig\Error\RuntimeError $e) {
                    throw $e->getPrevious();
                }
            };'),
        ));
        $ref = (int) $out;

        $data = $cloner->cloneVar($out);
        $dumper->dump($data, $out);
        rewind($out);
        $out = stream_get_contents($out);

        $this->assertTrue(false !== strpos($out, 'stream resource {@'.$ref));
        $this->assertTrue(false !== strpos($out, 'stream_type: "MEMORY"'));
        $this->assertTrue(false !== strpos($out, 'uri: "php://memory"'));
        $this->assertTrue(false !== strpos($out, 'ThrowingCasterException'));
        $this->assertTrue(false !== strpos($out, 'Unexpected Exception thrown from a caster: Foobar'));
    }

    public function testRefsInProperties()
    {
        $var = (object) array('foo' => 'foo');
        $var->bar = &$var->foo;

        $dumper = new CliDumper();
        $dumper->setColors(false);
        $cloner = new VarCloner();

        $out = fopen('php://memory', 'r+b');
        $data = $cloner->cloneVar($var);
        $dumper->dump($data, $out);
        rewind($out);
        $out = stream_get_contents($out);

        $r = \defined('HHVM_VERSION') ? '' : '#%d';
        $this->assertStringMatchesFormat(
            <<<EOTXT
{{$r}
  +"foo": &1 "foo"
  +"bar": &1 "foo"
}

EOTXT
            ,
            $out
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    #[RequiresPhp('5.6')]
    /**
     */
    public function testSpecialVars56()
    {
        $var = $this->getSpecialVars();

        $dump = $this->getDump($var);
        $this->assertTrue(false !== strpos($dump, '0 => array:1 ['));
        $this->assertTrue(false !== strpos($dump, '&1 array:1 [&1]'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    /**
     */
    public function testGlobalsNoExt()
    {
        $var = $this->getSpecialVars();
        unset($var[0]);
        $out = '';

        $dumper = new CliDumper(function ($line, $depth) use (&$out) {
            if ($depth >= 0) {
                $out .= str_repeat('  ', $depth).$line."\n";
            }
        });
        $dumper->setColors(false);
        $cloner = new VarCloner();

        $refl = new \ReflectionProperty($cloner, 'useExt');
        $refl->setValue($cloner, false);

        $data = $cloner->cloneVar($var);
        $dumper->dump($data);

        $this->assertTrue(false !== strpos($out, 'array:'));
        $this->assertTrue(false !== strpos($out, '1 =>'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    /**
     */
    public function testBuggyRefs()
    {
        if (\PHP_VERSION_ID >= 50600) {
            $this->markTestSkipped('PHP 5.6 fixed refs counting');
        }

        $var = $this->getSpecialVars();
        $var = $var[0];

        $dumper = new CliDumper();
        $dumper->setColors(false);
        $cloner = new VarCloner();

        $data = $cloner->cloneVar($var)->withMaxDepth(3);
        $out = '';
        $dumper->dump($data, function ($line, $depth) use (&$out) {
            if ($depth >= 0) {
                $out .= str_repeat('  ', $depth).$line."\n";
            }
        });

        $this->assertSame(
            <<<'EOTXT'
array:1 [
  0 => array:1 [
    0 => array:1 [
      0 => array:1 [ …1]
    ]
  ]
]

EOTXT
            ,
            $out
        );
    }

    private function getSpecialVars()
    {
        foreach (array_keys($GLOBALS) as $var) {
            if ('GLOBALS' !== $var) {
                unset($GLOBALS[$var]);
            }
        }

        $var = function &() {
            $var = array();
            $var[] = &$var;

            return $var;
        };

        // PHP 8.1+: $GLOBALS cannot be passed by reference; return value copy only.
        return array($var(), $GLOBALS);
    }
}
