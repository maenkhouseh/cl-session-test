<?php

namespace Tests\Unit;

use App\Services\ClickBuffer;
use Tests\TestCase;

class ClickBufferTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(ClickBuffer::class)->clear();
    }

    protected function tearDown(): void
    {
        app(ClickBuffer::class)->clear();
        parent::tearDown();
    }

    public function test_drains_fifo_in_bounded_batches_and_round_trips_unicode(): void
    {
        $buffer = app(ClickBuffer::class);
        foreach (range(1, 5) as $i) {
            $buffer->push(['n' => $i, 'city' => 'São Paulo/東京']);
        }

        $this->assertSame(5, $buffer->size());
        $first = $buffer->drain(2);
        $this->assertSame([1, 2], array_column($first, 'n'));
        $this->assertSame('São Paulo/東京', $first[0]['city']);
        $this->assertSame([3, 4, 5], array_column($buffer->drain(100), 'n'));
        $this->assertSame([], $buffer->drain(10));
    }
}
