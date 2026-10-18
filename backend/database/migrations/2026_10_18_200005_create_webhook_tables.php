<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('url');
            $table->text('secret'); // encrypted at rest; needed in clear to sign payloads
            $table->jsonb('triggers');
            $table->timestamp('disabled_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('last_failed_at')->nullable();
            $table->timestamps();
            $table->index('workspace_id');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event', 60);
            $table->jsonb('payload');
            $table->string('status', 10); // pending | success | failed
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();
            $table->index(['webhook_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
