<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->string('plan')->default('free');
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            // Usage metering for the current billing cycle.
            $table->unsignedBigInteger('links_usage')->default(0);
            $table->unsignedBigInteger('events_usage')->default(0);
            $table->timestamp('usage_reset_at')->nullable();
            $table->timestamps();
        });

        Schema::create('workspace_user', function (Blueprint $table) {
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamps();
            $table->primary(['workspace_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('workspace_invites', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role')->default('member');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['workspace_id', 'email']);
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key_hash', 64)->unique();
            $table->string('key_preview', 16);
            $table->json('scopes')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('workspace_invites');
        Schema::dropIfExists('workspace_user');
        Schema::dropIfExists('workspaces');
    }
};
