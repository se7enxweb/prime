<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Tests;



use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Debug\BufferingLogger;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Debug\Exception\ContextErrorException;

/**
 * ErrorHandlerTest.
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ErrorHandlerTest extends TestCase
{
    public function testRegister()
    {
        $handler = ErrorHandler::register();

        try {
            $this->assertInstanceOf('Symfony\Component\Debug\ErrorHandler', $handler);
            $this->assertSame($handler, ErrorHandler::register());

            $newHandler = new ErrorHandler();

            $this->assertSame($handler, ErrorHandler::register($newHandler, false));
            $h = set_error_handler('var_dump');
            restore_error_handler();
            $this->assertSame(array($handler, 'handleError'), $h);

            try {
                $this->assertSame($newHandler, ErrorHandler::register($newHandler, true));
                $h = set_error_handler('var_dump');
                restore_error_handler();
                $this->assertSame(array($newHandler, 'handleError'), $h);
            } catch (\Exception $e) {
            }

            restore_error_handler();
            restore_exception_handler();

            if (isset($e)) {
                throw $e;
            }
        } catch (\Exception $e) {
        }

        restore_error_handler();
        restore_exception_handler();

        if (isset($e)) {
            throw $e;
        }
    }

    public function testErrorGetLast()
    {
        $handler = ErrorHandler::register();
        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $handler->setDefaultLogger($logger);
        $handler->screamAt(E_ALL);

        try {
            @trigger_error('Hello', E_USER_WARNING);
            $expected = array(
                'type' => E_USER_WARNING,
                'message' => 'Hello',
                'file' => __FILE__,
                'line' => __LINE__ - 5,
            );
            $this->assertSame($expected, error_get_last());
        } catch (\Exception $e) {
            throw $e;
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testNotice()
    {
        error_reporting(E_ALL);
        ErrorHandler::register();

        try {
            self::triggerNotice($this);
            $this->fail('ContextErrorException expected');
        } catch (ContextErrorException $exception) {
            // if an exception is thrown, the test passed
            restore_error_handler();
            restore_exception_handler();

            $this->assertEquals(E_WARNING, $exception->getSeverity());
            $this->assertEquals(__FILE__, $exception->getFile());
            $this->assertMatchesRegularExpression('/^(Notice|Warning): Undefined variable[: $]+(foo|bar)/', $exception->getMessage());
            if (\PHP_VERSION_ID < 70200) {
                $this->assertArrayHasKey('foobar', $exception->getContext());
            }

            $trace = $exception->getTrace();
            $this->assertEquals(__FILE__, $trace[0]['file']);
            $this->assertEquals('Symfony\Component\Debug\ErrorHandler', $trace[0]['class']);
            $this->assertEquals('handleError', $trace[0]['function']);
            $this->assertEquals('->', $trace[0]['type']);

            $this->assertEquals(__FILE__, $trace[1]['file']);
            $this->assertEquals(__CLASS__, $trace[1]['class']);
            $this->assertEquals('triggerNotice', $trace[1]['function']);
            $this->assertEquals('::', $trace[1]['type']);

            $this->assertEquals(__FILE__, $trace[1]['file']);
            $this->assertEquals(__CLASS__, $trace[2]['class']);
            $this->assertEquals(__FUNCTION__, $trace[2]['function']);
            $this->assertEquals('->', $trace[2]['type']);
        } catch (\Exception $e) {
            restore_error_handler();
            restore_exception_handler();

            throw $e;
        }
    }

    // dummy function to test trace in error handler.
    private static function triggerNotice($that)
    {
        // dummy variable to check for in error handler.
        $foobar = 123;
        $that->assertSame('', $foo.$foo.$bar);
    }

    public function testConstruct()
    {
        try {
            $handler = ErrorHandler::register();
            $handler->throwAt(3, true);
            $this->assertEquals(3 | E_RECOVERABLE_ERROR | E_USER_ERROR, $handler->throwAt(0));

            restore_error_handler();
            restore_exception_handler();
        } catch (\Exception $e) {
            restore_error_handler();
            restore_exception_handler();

            throw $e;
        }
    }

    public function testDefaultLogger()
    {
        try {
            $handler = ErrorHandler::register();

            $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();

            $handler->setDefaultLogger($logger, E_NOTICE);
            $handler->setDefaultLogger($logger, array(E_USER_NOTICE => LogLevel::CRITICAL));

            $loggers = array(
                E_DEPRECATED => array(null, LogLevel::INFO),
                E_USER_DEPRECATED => array(null, LogLevel::INFO),
                E_NOTICE => array($logger, LogLevel::WARNING),
                E_USER_NOTICE => array($logger, LogLevel::CRITICAL),
                2048 => array(null, LogLevel::WARNING),
                E_WARNING => array(null, LogLevel::WARNING),
                E_USER_WARNING => array(null, LogLevel::WARNING),
                E_COMPILE_WARNING => array(null, LogLevel::WARNING),
                E_CORE_WARNING => array(null, LogLevel::WARNING),
                E_USER_ERROR => array(null, LogLevel::CRITICAL),
                E_RECOVERABLE_ERROR => array(null, LogLevel::CRITICAL),
                E_COMPILE_ERROR => array(null, LogLevel::CRITICAL),
                E_PARSE => array(null, LogLevel::CRITICAL),
                E_ERROR => array(null, LogLevel::CRITICAL),
                E_CORE_ERROR => array(null, LogLevel::CRITICAL),
            );
            $this->assertSame($loggers, $handler->setLoggers(array()));

            restore_error_handler();
            restore_exception_handler();
        } catch (\Exception $e) {
            restore_error_handler();
            restore_exception_handler();

            throw $e;
        }
    }

    public function testHandleError()
    {
        error_reporting(E_ALL);
        try {
            $handler = ErrorHandler::register();
            $handler->throwAt(0, true);
            $this->assertFalse($handler->handleError(0, 'foo', 'foo.php', 12, array()));

            restore_error_handler();
            restore_exception_handler();

            $handler = ErrorHandler::register();
            $handler->throwAt(3, true);
            $this->assertFalse($handler->handleError(4, 'foo', 'foo.php', 12, array()));

            restore_error_handler();
            restore_exception_handler();

            $handler = ErrorHandler::register();
            $handler->throwAt(3, true);
            try {
                $handler->handleError(4, 'foo', 'foo.php', 12, array());
            } catch (\ErrorException $e) {
                $this->assertSame('Parse Error: foo', $e->getMessage());
                $this->assertSame(4, $e->getSeverity());
                $this->assertSame('foo.php', $e->getFile());
                $this->assertSame(12, $e->getLine());
            }

            restore_error_handler();
            restore_exception_handler();

            $handler = ErrorHandler::register();
            $handler->throwAt(E_USER_DEPRECATED, true);
            $this->assertFalse($handler->handleError(E_USER_DEPRECATED, 'foo', 'foo.php', 12, array()));

            restore_error_handler();
            restore_exception_handler();

            $handler = ErrorHandler::register();
            $handler->throwAt(E_DEPRECATED, true);
            $this->assertFalse($handler->handleError(E_DEPRECATED, 'foo', 'foo.php', 12, array()));

            restore_error_handler();
            restore_exception_handler();

            $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();

            $that = $this;
            $warnArgCheck = function ($logLevel, $message, $context) use ($that) {
                $that->assertEquals('info', $logLevel);
                $that->assertEquals('foo', $message);
                $that->assertArrayHasKey('type', $context);
                $that->assertEquals($context['type'], E_USER_DEPRECATED);
                $that->assertArrayHasKey('stack', $context);
                $that->assertIsArray($context['stack']);
            };

            $logger
                ->expects($this->once())
                ->method('log')
                ->willReturnCallback($warnArgCheck)
            ;

            $handler = ErrorHandler::register();
            $handler->setDefaultLogger($logger, E_USER_DEPRECATED);
            $this->assertTrue($handler->handleError(E_USER_DEPRECATED, 'foo', 'foo.php', 12, array()));

            restore_error_handler();
            restore_exception_handler();

            $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();

            $that = $this;
            $logArgCheck = function ($level, $message, $context) use ($that) {
                $that->assertMatchesRegularExpression('/Undefined variable[: $]+undefVar/', $message);
                $that->assertArrayHasKey('type', $context);
                $that->assertContains($context['type'], [E_NOTICE, E_WARNING]);
            };

            $logger
                ->expects($this->once())
                ->method('log')
                ->willReturnCallback($logArgCheck)
            ;

            $handler = ErrorHandler::register();
            $handler->setDefaultLogger($logger, E_WARNING);
            $handler->screamAt(E_WARNING);
            unset($undefVar);
            @$undefVar++;

            restore_error_handler();
            restore_exception_handler();
        } catch (\Exception $e) {
            restore_error_handler();
            restore_exception_handler();

            throw $e;
        }
    }

    public function testHandleUserError()
    {
        if (\PHP_VERSION_ID >= 80000) {
            $this->markTestSkipped('PHP 8 no longer passes local variable context to error handlers');
        }
        try {
            $handler = ErrorHandler::register();
            $handler->throwAt(0, true);

            $e = null;
            $x = new \Exception('Foo');

            try {
                $f = new Fixtures\ToStringThrower($x);
                $f .= ''; // Trigger $f->__toString()
            } catch (\Exception $e) {
            }

            $this->assertSame($x, $e);

            restore_error_handler();
            restore_exception_handler();
        } catch (\Exception $e) {
            restore_error_handler();
            restore_exception_handler();

            throw $e;
        }
    }

    public function testHandleDeprecation()
    {
        $that = $this;
        $logArgCheck = function ($level, $message, $context) use ($that) {
            $that->assertEquals(LogLevel::INFO, $level);
            $that->assertArrayHasKey('level', $context);
            $expectedBits = E_RECOVERABLE_ERROR | E_USER_ERROR | E_DEPRECATED | E_USER_DEPRECATED;
            $that->assertEquals($expectedBits, $context['level'] & $expectedBits);
            $that->assertArrayHasKey('stack', $context);
        };

        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $logger
            ->expects($this->once())
            ->method('log')
            ->willReturnCallback($logArgCheck)
        ;

        $handler = new ErrorHandler();
        $handler->setDefaultLogger($logger);
        @$handler->handleError(E_USER_DEPRECATED, 'Foo deprecation', __FILE__, __LINE__, array());
    }

    #[Group('no-hhvm')]
    /**
     */
    public function testHandleException()
    {
        try {
            $handler = ErrorHandler::register();

            $exception = new \Exception('foo');

            $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();

            $that = $this;
            $logArgCheck = function ($level, $message, $context) use ($that) {
                $that->assertEquals('Uncaught Exception: foo', $message);
                $that->assertArrayHasKey('type', $context);
                $that->assertEquals($context['type'], E_ERROR);
            };

            $logger
                ->expects($this->exactly(2))
                ->method('log')
                ->willReturnCallback($logArgCheck)
            ;

            $handler->setDefaultLogger($logger, E_ERROR);

            try {
                $handler->handleException($exception);
                $this->fail('Exception expected');
            } catch (\Exception $e) {
                $this->assertSame($exception, $e);
            }

            $that = $this;
            $handler->setExceptionHandler(function ($e) use ($exception, $that) {
                $that->assertSame($exception, $e);
            });

            $handler->handleException($exception);

            restore_error_handler();
            restore_exception_handler();
        } catch (\Exception $e) {
            restore_error_handler();
            restore_exception_handler();

            throw $e;
        }
    }

    public function testErrorStacking()
    {
        try {
            $handler = ErrorHandler::register();
            $handler->screamAt(E_USER_WARNING);

            $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();

            $logger
                ->expects($this->exactly(2))
                ->method('log')
                ->willReturnCallback(function ($level, $message) {
                    static $calls = 0;
                    $calls++;
                    if (1 === $calls) {
                        $this->assertEquals(LogLevel::WARNING, $level);
                        $this->assertEquals('Dummy log', $message);
                    } else {
                        $this->assertEquals(LogLevel::DEBUG, $level);
                        $this->assertEquals('Silenced warning', $message);
                    }
                })
            ;

            $handler->setDefaultLogger($logger, array(E_USER_WARNING => LogLevel::WARNING));

            ErrorHandler::stackErrors();
            @trigger_error('Silenced warning', E_USER_WARNING);
            $logger->log(LogLevel::WARNING, 'Dummy log');
            ErrorHandler::unstackErrors();

            restore_error_handler();
            restore_exception_handler();
        } catch (\Exception $e) {
            restore_error_handler();
            restore_exception_handler();

            throw $e;
        }
    }

    public function testBootstrappingLogger()
    {
        $bootLogger = new BufferingLogger();
        $handler = new ErrorHandler($bootLogger);

        $loggers = array(
            E_DEPRECATED => array($bootLogger, LogLevel::INFO),
            E_USER_DEPRECATED => array($bootLogger, LogLevel::INFO),
            E_NOTICE => array($bootLogger, LogLevel::WARNING),
            E_USER_NOTICE => array($bootLogger, LogLevel::WARNING),
            2048 => array($bootLogger, LogLevel::WARNING),
            E_WARNING => array($bootLogger, LogLevel::WARNING),
            E_USER_WARNING => array($bootLogger, LogLevel::WARNING),
            E_COMPILE_WARNING => array($bootLogger, LogLevel::WARNING),
            E_CORE_WARNING => array($bootLogger, LogLevel::WARNING),
            E_USER_ERROR => array($bootLogger, LogLevel::CRITICAL),
            E_RECOVERABLE_ERROR => array($bootLogger, LogLevel::CRITICAL),
            E_COMPILE_ERROR => array($bootLogger, LogLevel::CRITICAL),
            E_PARSE => array($bootLogger, LogLevel::CRITICAL),
            E_ERROR => array($bootLogger, LogLevel::CRITICAL),
            E_CORE_ERROR => array($bootLogger, LogLevel::CRITICAL),
        );

        $this->assertSame($loggers, $handler->setLoggers(array()));

        $handler->handleError(E_DEPRECATED, 'Foo message', __FILE__, 123, array());
        $expectedLog = array(LogLevel::INFO, 'Foo message', array('type' => E_DEPRECATED, 'file' => __FILE__, 'line' => 123, 'level' => error_reporting() | E_RECOVERABLE_ERROR | E_USER_ERROR | E_DEPRECATED | E_USER_DEPRECATED));

        $logs = $bootLogger->cleanLogs();
        unset($logs[0][2]['stack']);

        $this->assertSame(array($expectedLog), $logs);

        $bootLogger->log($expectedLog[0], $expectedLog[1], $expectedLog[2]);

        $mockLogger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $mockLogger->expects($this->once())
            ->method('log')
            ->with(LogLevel::WARNING, 'Foo message', $expectedLog[2]);

        $handler->setLoggers(array(E_DEPRECATED => array($mockLogger, LogLevel::WARNING)));
    }

    #[Group('no-hhvm')]
    /**
     */
    public function testHandleFatalError()
    {
        try {
            $handler = ErrorHandler::register();

            $error = array(
                'type' => E_PARSE,
                'message' => 'foo',
                'file' => 'bar',
                'line' => 123,
            );

            $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();

            $that = $this;
            $logArgCheck = function ($level, $message, $context) use ($that) {
                $that->assertEquals('Fatal Parse Error: foo', $message);
                $that->assertArrayHasKey('type', $context);
                $that->assertEquals($context['type'], E_PARSE);
            };

            $logger
                ->expects($this->once())
                ->method('log')
                ->willReturnCallback($logArgCheck)
            ;

            $handler->setDefaultLogger($logger, E_PARSE);

            $handler->handleFatalError($error);

            restore_error_handler();
            restore_exception_handler();
        } catch (\Exception $e) {
            restore_error_handler();
            restore_exception_handler();

            throw $e;
        }
    }

    #[RequiresPhp('7')]
    /**
     */
    public function testHandleErrorException()
    {
        $exception = new \Error("Class 'Foo' not found");

        $handler = new ErrorHandler();
        $handler->setExceptionHandler(function () use (&$args) {
            $args = \func_get_args();
        });

        $handler->handleException($exception);

        $this->assertInstanceOf('Symfony\Component\Debug\Exception\ClassNotFoundException', $args[0]);
        $this->assertStringStartsWith("Attempted to load class \"Foo\" from the global namespace.\nDid you forget a \"use\" statement", $args[0]->getMessage());
    }

    #[Group('no-hhvm')]
    /**
     */
    public function testHandleFatalErrorOnHHVM()
    {
        if (\PHP_VERSION_ID >= 80500) {
            $this->markTestSkipped('Skipped on PHP 8.5+: this HHVM emulation test can hang while probing exception handlers.');
        }

        try {
            $handler = ErrorHandler::register();

            $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
            $logger
                ->expects($this->once())
                ->method('log')
                ->with(
                    $this->equalTo(LogLevel::CRITICAL),
                    $this->equalTo('Fatal Error: foo'),
                    $this->equalTo(array(
                        'type' => 1,
                        'file' => 'bar',
                        'line' => 123,
                        'level' => -1,
                        'stack' => array(456),
                    ))
                )
            ;

            $handler->setDefaultLogger($logger, E_ERROR);

            $error = array(
                'type' => E_ERROR + 0x1000000, // This error level is used by HHVM for fatal errors
                'message' => 'foo',
                'file' => 'bar',
                'line' => 123,
                'context' => array(123),
                'backtrace' => array(456),
            );

            \call_user_func_array(array($handler, 'handleError'), array_values($error));
            $handler->handleFatalError($error);

            restore_error_handler();
            restore_exception_handler();
        } catch (\Exception $e) {
            restore_error_handler();
            restore_exception_handler();

            throw $e;
        }
    }

    #[Group('legacy')]    public function testLegacyInterface()
    {
        error_reporting(E_ALL);
        try {
            $handler = ErrorHandler::register(0);
            $this->assertFalse($handler->handle(0, 'foo', 'foo.php', 12, array()));

            restore_error_handler();
            restore_exception_handler();

            $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();

            $that = $this;
            $logArgCheck = function ($level, $message, $context) use ($that) {
                $that->assertMatchesRegularExpression('/Undefined variable[: $]+undefVar/', $message);
                $that->assertArrayHasKey('type', $context);
                $that->assertContains($context['type'], [E_NOTICE, E_WARNING]);
            };

            $logger
                ->expects($this->once())
                ->method('log')
                ->willReturnCallback($logArgCheck)
            ;

            $handler = ErrorHandler::register(E_WARNING);
            @$handler->setLogger($logger, 'scream');
            unset($undefVar);
            @$undefVar++;

            restore_error_handler();
            restore_exception_handler();
        } catch (\Exception $e) {
            restore_error_handler();
            restore_exception_handler();

            throw $e;
        }
    }

    #[Group('no-hhvm')]
    /**
     */
    public function testCustomExceptionHandler()
    {
        $this->expectException(\Exception::class);

        $handler = new ErrorHandler();
        $handler->setExceptionHandler(function ($e) use ($handler) {
            $handler->handleException($e);
        });

        $handler->handleException(new \Exception());
    }
}
