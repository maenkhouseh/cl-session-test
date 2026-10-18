<?php

use App\Jobs\FlushClickBuffer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new FlushClickBuffer)->everyTenSeconds()->withoutOverlapping();
Schedule::command('events:partitions')->daily();
Schedule::command('usage:reset')->hourly();
