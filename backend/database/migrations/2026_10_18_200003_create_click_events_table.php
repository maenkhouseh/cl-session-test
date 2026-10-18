<?php

use App\Services\EventPartitions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only fact table, range-partitioned by month so retention is DROP PARTITION and
        // time-bounded analytics only touch the partitions they need.
        DB::statement(<<<'SQL'
            CREATE TABLE click_events (
                id            bigint GENERATED ALWAYS AS IDENTITY,
                occurred_at   timestamptz NOT NULL,
                workspace_id  char(26) NOT NULL,
                link_id       char(26) NOT NULL,
                click_id      varchar(32) NOT NULL,
                is_unique     boolean NOT NULL DEFAULT true,
                url           text NOT NULL,
                country       char(2),
                region        varchar(120),
                city          varchar(120),
                continent     char(2),
                device        varchar(20),
                browser       varchar(60),
                os            varchar(60),
                referer       varchar(255),
                referer_url   text,
                qr            boolean NOT NULL DEFAULT false,
                utm_source    varchar(190),
                utm_medium    varchar(190),
                utm_campaign  varchar(190),
                utm_term      varchar(190),
                utm_content   varchar(190),
                PRIMARY KEY (id, occurred_at)
            ) PARTITION BY RANGE (occurred_at)
        SQL);

        // Every analytics query is "this workspace (and maybe this link) in a time window".
        DB::statement('CREATE INDEX click_events_workspace_time_idx ON click_events (workspace_id, occurred_at DESC)');
        DB::statement('CREATE INDEX click_events_link_time_idx ON click_events (link_id, occurred_at DESC)');
        DB::statement('CREATE INDEX click_events_click_id_idx ON click_events (click_id)');
        // Cheap range pruning for unfiltered scans over very large partitions.
        DB::statement('CREATE INDEX click_events_time_brin_idx ON click_events USING brin (occurred_at)');

        app(EventPartitions::class)->ensure('click_events', monthsAhead: 3);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS click_events');
    }
};
