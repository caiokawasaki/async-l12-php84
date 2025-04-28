<?php

namespace Spatie\Async\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Spatie\Async\Pool;

class PoolStatusTest extends TestCase
{

    public function testItCanShowATextualStatus()
    {
        $pool = Pool::create();

        $pool->add(new MyTask());

        $this->assertStringContainsString('finished: 0', (string)$pool->status());

        await($pool);

        $this->assertStringContainsString('finished: 1', (string)$pool->status());
    }

    public function testItCanShowATextualFailedStatus()
    {
        $pool = Pool::create();

        foreach (range(1, 5) as $i) {
            $pool->add(function() {
                throw new Exception('Test');
            })->catch(function() {
                // Do nothing
            });
        }

        $pool->wait();

        $this->assertStringContainsString('finished: 0', (string)$pool->status());
        $this->assertStringContainsString('failed: 5', (string)$pool->status());
        $this->assertStringContainsString('failed with Exception: Test', (string)$pool->status());
    }

    public function testItCanShowTimeoutStatus()
    {
        $pool = Pool::create()->timeout(0);

        foreach (range(1, 5) as $i) {
            $pool->add(function() {
                sleep(1000);
            });
        }

        $pool->wait();

        $this->assertStringContainsString('timeout: 5', (string)$pool->status());
    }

}
