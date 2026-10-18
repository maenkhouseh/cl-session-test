<?php

namespace App\Jobs;

use App\Actions\Links\FlushClicks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FlushClickBuffer implements ShouldQueue
{
    use Queueable;

    /** Upper bound per run so one tick never monopolises a worker. */
    private const MAX_BATCHES = 20;

    public function handle(FlushClicks $flush): void
    {
        for ($i = 0; $i < self::MAX_BATCHES; $i++) {
            if ($flush(1000) === 0) {
                break;
            }
        }
    }
}
