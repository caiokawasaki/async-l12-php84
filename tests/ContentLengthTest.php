<?php

namespace Spatie\Async\Tests;

use Spatie\Async\Output\ParallelError;
use Spatie\Async\Pool;

class ContentLengthTest extends TestCase
{

    public function testItCanIncreaseMaxContentLength()
    {
        $pool = Pool::create();

        $longerContentLength = 1024 * 100;

        $pool->add(new MyTask(), $longerContentLength);

        $this->assertStringContainsString('finished: 0', (string)$pool->status());

        await($pool);

        $this->assertStringContainsString('finished: 1', (string)$pool->status());
    }

    public function testItCanDecreaseMaxContentLength()
    {
        $pool = Pool::create();

        $shorterContentLength = 1024;

        $pool->add(new MyTask(), $shorterContentLength);

        $this->assertStringContainsString('finished: 0', (string)$pool->status());

        await($pool);

        $this->assertStringContainsString('finished: 1', (string)$pool->status());
    }

    public function testItCanThrowErrorWithIncreasedMaxContentLength()
    {
        $pool = Pool::create();

        $longerContentLength = 1024 * 100;

        $pool->add(function() {
            return random_bytes(1024 * 1000);
        }, $longerContentLength)
            ->catch(function(ParallelError $e) use ($longerContentLength) {
                $message = "/The output returned by this child process is too large. The serialized output may only be $longerContentLength bytes long./";
                $this->assertMatchesRegularExpression($message, $e->getMessage());
            });

        await($pool);
    }

    public function testItCanThrowErrorWithDecreasedMaxContentLength()
    {
        $pool = Pool::create();

        $longerContentLength = 1024;

        $pool->add(function() {
            return random_bytes(1024 * 100);
        }, $longerContentLength)
            ->catch(function(ParallelError $e) use ($longerContentLength) {
                $message = "/The output returned by this child process is too large. The serialized output may only be $longerContentLength bytes long./";
                $this->assertMatchesRegularExpression($message, $e->getMessage());
            });

        await($pool);
    }

}
