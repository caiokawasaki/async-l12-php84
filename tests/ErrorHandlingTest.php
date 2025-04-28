<?php

namespace Spatie\Async\Tests;

use Error;
use Exception;
use ParseError;
use Spatie\Async\Output\ParallelError;
use Spatie\Async\Output\ParallelException;
use Spatie\Async\Pool;

class ErrorHandlingTest extends TestCase
{

    public function testItCanHandleExceptionViaCatchCallback()
    {
        $pool = Pool::create();

        foreach (range(1, 5) as $i) {
            $pool->add(function() {
                throw new MyException('test');
            })->catch(function(MyException $e) {
                $this->assertMatchesRegularExpression('/test/', $e->getMessage());
            });
        }

        $pool->wait();

        $this->assertCount(5, $pool->getFailed(), (string)$pool->status());
    }

    public function testItCanHandleComplexExceptionsViaCatchCallback()
    {
        $pool = Pool::create();

        $originalExceptionCount = 0;
        $fallbackExceptionCount = 0;

        $pool
            ->add(function() {
                throw new MyExceptionWithAComplexArgument('test', (object)['error' => 'wrong query']);
            })
            ->catch(function(MyExceptionWithAComplexArgument $e) use (&$originalExceptionCount) {
                $originalExceptionCount += 1;
            })
            ->catch(function(ParallelException $e) use (&$fallbackExceptionCount) {
                $fallbackExceptionCount += 1;
                $this->assertEquals('test', $e->getMessage());
                $this->assertEquals(MyExceptionWithAComplexArgument::class, $e->getOriginalClass());
            });

        $pool
            ->add(function() use (&$originalExceptionCount) {
                throw new MyExceptionWithAComplexFirstArgument((object)['error' => 'wrong query'], 'test');
            })
            ->catch(function(MyExceptionWithAComplexFirstArgument $e) use (&$originalExceptionCount) {
                $originalExceptionCount += 1;
            })
            ->catch(function(ParallelException $e) use (&$fallbackExceptionCount) {
                $fallbackExceptionCount += 1;
                $this->assertEquals('test', $e->getMessage());
                $this->assertEquals(MyExceptionWithAComplexFirstArgument::class, $e->getOriginalClass());
            });

        $pool->wait();

        $this->assertCount(2, $pool->getFailed(), (string)$pool->status());
        $this->assertEquals(0, $originalExceptionCount);
        $this->assertEquals(2, $fallbackExceptionCount);
    }

    public function testItCanHandleTypedExceptionsViaCatchCallback()
    {
        $pool = Pool::create();

        $myExceptionCount = 0;

        $otherExceptionCount = 0;

        $exceptionCount = 0;

        foreach (range(1, 5) as $i) {
            $pool
                ->add(function() {
                    throw new MyException('test');
                })
                ->catch(function(MyException $e) use (&$myExceptionCount) {
                    $this->assertMatchesRegularExpression('/test/', $e->getMessage());

                    $myExceptionCount += 1;
                })
                ->catch(function(OtherException $e) use (&$otherExceptionCount) {
                    $otherExceptionCount += 1;
                })
                ->catch(function(Exception $e) use (&$exceptionCount) {
                    $exceptionCount += 1;
                });
        }

        $pool->wait();

        $this->assertEquals(5, $myExceptionCount);
        $this->assertEquals(0, $otherExceptionCount);
        $this->assertEquals(0, $exceptionCount);
        $this->assertCount(5, $pool->getFailed(), (string)$pool->status());
    }

    public function testItThrowsTheExceptionIfNoCatchCallback()
    {
        $this->expectException(MyException::class);
        $this->expectExceptionMessageMatches('/test/');

        $pool = Pool::create();

        $pool->add(function() {
            throw new MyException('test');
        });

        $pool->wait();
    }

    public function testItThrowsFatalErrors()
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessageMatches('/test/');

        $pool = Pool::create();

        $pool->add(function() {
            throw new Error('test');
        });

        $pool->wait();
    }

    public function testItKeepsTheOriginalTrace()
    {
        $pool = Pool::create();

        $pool->add(function() {
            $myClass = new MyClass();

            $myClass->throwException();
        })->catch(function(MyException $exception) {
            $this->assertStringContainsString('Spatie\Async\Tests\MyClass->throwException()', $exception->getMessage());
        });

        $pool->wait();
    }

    public function testItHandlesStderrAsParallelError()
    {
        $pool = Pool::create();

        $pool->add(function() {
            fwrite(STDERR, 'test');
        })->catch(function(ParallelError $error) {
            $this->assertStringContainsString('test', $error->getMessage());
        });

        $pool->wait();
    }

    public function testItHandlesStdoutAsParallelError()
    {
        $pool = Pool::create();

        $pool->add(function() {
            fwrite(STDOUT, 'test');
        })->then(function($output) {
            $this->fail('Child process output did not error on faulty output');
        })->catch(function(ParallelError $error) {
            $this->assertStringContainsString('test', $error->getMessage());
        });

        $pool->wait();
    }

    public function testDeepSyntaxErrorsAreThrown()
    {
        $pool = Pool::create();

        $pool->add(function() {
            new ClassWithSyntaxError();
        })->catch(function($error) {
            $this->assertInstanceOf(ParseError::class, $error);
        });

        $pool->wait();
    }

    public function testItCanHandleSynchronousException()
    {
        Pool::$forceSynchronous = true;

        $pool = Pool::create();

        $pool->add(function() {
            throw new MyException('test');
        })->catch(function(MyException $e) {
            $this->assertMatchesRegularExpression('/test/', $e->getMessage());
        });

        $pool->wait();

        Pool::$forceSynchronous = false;
    }

}
