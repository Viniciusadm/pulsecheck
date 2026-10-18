<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_daily_uptimes', function (Blueprint $table) {
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_checks');
            $table->unsignedInteger('successful_checks');
            $table->unsignedBigInteger('latency_sum_ms')->default(0);
            $table->unsignedInteger('latency_samples')->default(0);

            $table->primary(['monitor_id', 'date']);
            $table->index('date');
        });

        DB::statement(<<<'SQL'
            INSERT INTO monitor_daily_uptimes (monitor_id, date, total_checks, successful_checks, latency_sum_ms, latency_samples)
            SELECT
                monitor_id,
                (started_at AT TIME ZONE 'UTC')::date,
                count(*),
                count(*) FILTER (WHERE is_success),
                coalesce(sum(latency_ms) FILTER (WHERE is_success), 0),
                count(latency_ms) FILTER (WHERE is_success)
            FROM check_results
            WHERE started_at < date_trunc('day', now() AT TIME ZONE 'UTC') AT TIME ZONE 'UTC'
            GROUP BY 1, 2
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_daily_uptimes');
    }
};
