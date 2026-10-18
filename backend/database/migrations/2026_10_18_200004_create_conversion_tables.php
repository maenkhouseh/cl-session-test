<?php

use App\Services\EventPartitions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Dimension columns copied from the originating click so conversions filter exactly like clicks. */
    private const DIMENSIONS = <<<'SQL'
        url           text,
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
    SQL;

    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 255);
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->text('avatar')->nullable();
            $table->char('country', 2)->nullable();
            // First-touch attribution: the link/click that produced this customer.
            $table->char('link_id', 26)->nullable();
            $table->string('click_id', 32)->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'external_id']);
            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'link_id']);
        });
        DB::statement('CREATE INDEX customers_email_idx ON customers (workspace_id, lower(email))');
        DB::statement('CREATE INDEX customers_search_trgm_idx ON customers USING gin ((coalesce(name, \'\') || \' \' || coalesce(email, \'\')) gin_trgm_ops)');

        // Partitioned tables cannot carry a unique index without the partition key, so idempotency
        // ("one sale per invoice", "one lead per customer+event") is enforced in this small table.
        Schema::create('event_dedupe', function (Blueprint $table) {
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('dedupe_key', 255);
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['workspace_id', 'dedupe_key']);
        });

        DB::statement('CREATE TABLE lead_events (
            id            bigint GENERATED ALWAYS AS IDENTITY,
            occurred_at   timestamptz NOT NULL,
            workspace_id  char(26) NOT NULL,
            link_id       char(26) NOT NULL,
            customer_id   char(26) NOT NULL,
            click_id      varchar(32) NOT NULL,
            event_name    varchar(255) NOT NULL,
            metadata      jsonb,
            '.self::DIMENSIONS.'
            PRIMARY KEY (id, occurred_at)
        ) PARTITION BY RANGE (occurred_at)');

        DB::statement('CREATE TABLE sale_events (
            id                 bigint GENERATED ALWAYS AS IDENTITY,
            occurred_at        timestamptz NOT NULL,
            workspace_id       char(26) NOT NULL,
            link_id            char(26) NOT NULL,
            customer_id        char(26) NOT NULL,
            click_id           varchar(32) NOT NULL,
            event_name         varchar(255) NOT NULL,
            invoice_id         varchar(255),
            amount             bigint NOT NULL,
            currency           char(3) NOT NULL DEFAULT \'usd\',
            payment_processor  varchar(40) NOT NULL DEFAULT \'custom\',
            metadata           jsonb,
            '.self::DIMENSIONS.'
            PRIMARY KEY (id, occurred_at)
        ) PARTITION BY RANGE (occurred_at)');

        foreach (['lead_events', 'sale_events'] as $table) {
            DB::statement("CREATE INDEX {$table}_workspace_time_idx ON {$table} (workspace_id, occurred_at DESC)");
            DB::statement("CREATE INDEX {$table}_link_time_idx ON {$table} (link_id, occurred_at DESC)");
            DB::statement("CREATE INDEX {$table}_customer_time_idx ON {$table} (customer_id, occurred_at DESC)");
            DB::statement("CREATE INDEX {$table}_time_brin_idx ON {$table} USING brin (occurred_at)");
            app(EventPartitions::class)->ensure($table, monthsAhead: 3);
        }

        Schema::create('shared_dashboards', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('link_id')->constrained()->cascadeOnDelete();
            $table->string('password')->nullable();
            $table->boolean('do_index')->default(false);
            $table->timestamps();
            $table->unique('link_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_dashboards');
        DB::statement('DROP TABLE IF EXISTS sale_events');
        DB::statement('DROP TABLE IF EXISTS lead_events');
        Schema::dropIfExists('event_dedupe');
        Schema::dropIfExists('customers');
    }
};
