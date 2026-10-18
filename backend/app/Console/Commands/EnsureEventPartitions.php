<?php

namespace App\Console\Commands;

use App\Services\EventPartitions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:partitions {--ahead=3 : Months ahead to create}')]
#[Description('Create upcoming monthly partitions for the analytics event tables')]
class EnsureEventPartitions extends Command
{
    public function handle(EventPartitions $partitions): int
    {
        foreach (['click_events', 'lead_events', 'sale_events'] as $table) {
            $created = $partitions->ensure($table, (int) $this->option('ahead'));
            $this->info("{$table}: ".($created ? implode(', ', $created) : 'up to date'));
        }

        return self::SUCCESS;
    }
}
