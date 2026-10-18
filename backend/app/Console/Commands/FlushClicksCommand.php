<?php

namespace App\Console\Commands;

use App\Actions\Links\FlushClicks;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('clicks:flush {--batch=1000 : Events per batch}')]
#[Description('Drain the click buffer into the events table right now (normally done every 10 s by the scheduler)')]
class FlushClicksCommand extends Command
{
    public function handle(FlushClicks $flush): int
    {
        $total = 0;

        while (($count = $flush((int) $this->option('batch'))) > 0) {
            $total += $count;
        }

        $this->info("Flushed {$total} click event(s).");

        return self::SUCCESS;
    }
}
