<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('domains', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('archived')->default(false);
            $table->text('placeholder_url')->nullable();
            $table->text('not_found_url')->nullable();
            $table->text('expired_url')->nullable();
            $table->timestamps();
            $table->index('workspace_id');
        });
        // At most one primary domain per workspace.
        DB::statement('CREATE UNIQUE INDEX domains_one_primary_per_workspace ON domains (workspace_id) WHERE is_primary');

        Schema::create('tags', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('color', 20)->default('blue');
            $table->timestamps();
        });
        DB::statement('CREATE UNIQUE INDEX tags_workspace_name_unique ON tags (workspace_id, lower(name))');

        Schema::create('folders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 190);
            $table->string('description', 500)->nullable();
            // Default access for workspace members: write | read | none.
            $table->string('access_level', 10)->default('write');
            $table->timestamps();
        });
        DB::statement('CREATE UNIQUE INDEX folders_workspace_name_unique ON folders (workspace_id, lower(name))');

        Schema::create('utm_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 190);
            foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ref'] as $col) {
                $table->string($col, 190)->nullable();
            }
            $table->timestamps();
        });
        DB::statement('CREATE UNIQUE INDEX utm_templates_workspace_name_unique ON utm_templates (workspace_id, lower(name))');

        Schema::create('links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('folder_id')->nullable()->constrained()->nullOnDelete();

            $table->string('domain');
            $table->string('key', 190);
            $table->text('url');
            $table->string('external_id', 255)->nullable();
            $table->string('tenant_id', 255)->nullable();

            $table->boolean('track_conversion')->default(false);
            $table->boolean('archived')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->text('expired_url')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->string('password')->nullable();
            $table->boolean('proxy')->default(false);
            $table->boolean('rewrite')->default(false);
            $table->boolean('do_index')->default(false);
            $table->boolean('public_stats')->default(false);

            $table->string('title', 255)->nullable();
            $table->string('description', 500)->nullable();
            $table->text('image')->nullable();
            $table->text('video')->nullable();
            $table->text('ios')->nullable();
            $table->text('android')->nullable();
            $table->jsonb('geo')->nullable();
            $table->text('comments')->nullable();
            foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ref'] as $col) {
                $table->string($col, 190)->nullable();
            }

            $table->jsonb('test_variants')->nullable();
            $table->timestamp('test_started_at')->nullable();
            $table->timestamp('test_completed_at')->nullable();

            // Denormalised counters: list sorting by clicks/revenue is an index scan, not an aggregate.
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('leads')->default(0);
            $table->unsignedBigInteger('sales')->default(0);
            $table->unsignedBigInteger('sale_amount')->default(0); // cents
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamps();
        });

        // Redirect hot-path lookup: case-insensitive key per domain.
        DB::statement('CREATE UNIQUE INDEX links_domain_key_unique ON links (domain, lower(key))');
        DB::statement('CREATE UNIQUE INDEX links_workspace_external_id_unique ON links (workspace_id, external_id) WHERE external_id IS NOT NULL');
        // Dashboard list: default sort + the common filter/sort combinations.
        DB::statement('CREATE INDEX links_workspace_created_idx ON links (workspace_id, archived, created_at DESC, id DESC)');
        DB::statement('CREATE INDEX links_workspace_clicks_idx ON links (workspace_id, archived, clicks DESC, id DESC)');
        DB::statement('CREATE INDEX links_workspace_sale_amount_idx ON links (workspace_id, archived, sale_amount DESC, id DESC)');
        DB::statement('CREATE INDEX links_workspace_last_clicked_idx ON links (workspace_id, archived, last_clicked_at DESC NULLS LAST, id DESC)');
        DB::statement('CREATE INDEX links_workspace_folder_idx ON links (workspace_id, folder_id, created_at DESC)');
        DB::statement('CREATE INDEX links_workspace_domain_idx ON links (workspace_id, domain, created_at DESC)');
        DB::statement('CREATE INDEX links_workspace_tenant_idx ON links (workspace_id, tenant_id) WHERE tenant_id IS NOT NULL');
        // Substring search on the dashboard search box.
        DB::statement('CREATE INDEX links_key_trgm_idx ON links USING gin (key gin_trgm_ops)');
        DB::statement('CREATE INDEX links_url_trgm_idx ON links USING gin (url gin_trgm_ops)');

        Schema::create('link_tag', function (Blueprint $table) {
            $table->foreignUlid('link_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['link_id', 'tag_id']);
            $table->index(['tag_id', 'link_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_tag');
        Schema::dropIfExists('links');
        Schema::dropIfExists('utm_templates');
        Schema::dropIfExists('folders');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('domains');
    }
};
