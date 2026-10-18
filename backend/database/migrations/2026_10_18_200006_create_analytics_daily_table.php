<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daily (UTC) pre-aggregate per link. Totals and long-range timeseries read this instead of scanning
        // millions of events; raw events are only touched for the partial days at the edges of a range.
        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->char('workspace_id', 26);
            $table->char('link_id', 26);
            $table->date('day');
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('unique_clicks')->default(0);
            $table->unsignedBigInteger('leads')->default(0);
            $table->unsignedBigInteger('sales')->default(0);
            $table->unsignedBigInteger('sale_amount')->default(0);
            $table->primary(['link_id', 'day']);
            $table->index(['workspace_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily');
    }
};
