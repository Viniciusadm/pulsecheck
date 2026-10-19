<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_hourly_stats', function (Blueprint $table) {
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('hour');
            $table->unsignedInteger('checks');
            $table->unsignedInteger('successful_checks');
            $table->unsignedBigInteger('latency_sum_ms')->default(0);
            $table->unsignedInteger('latency_samples')->default(0);
            $table->unsignedInteger('p95_latency_ms')->nullable();

            $table->primary(['monitor_id', 'hour']);
            $table->index('hour');
        });

        DB::statement(<<<'SQL'
            INSERT INTO monitor_hourly_stats (monitor_id, hour, checks, successful_checks, latency_sum_ms, latency_samples, p95_latency_ms)
            SELECT
                monitor_id,
                date_bin('1 hour'::interval, started_at, timestamptz '1970-01-01 00:00:00+00'),
                count(*),
                count(*) FILTER (WHERE is_success),
                coalesce(sum(latency_ms) FILTER (WHERE is_success), 0),
                count(latency_ms) FILTER (WHERE is_success),
                round(percentile_cont(0.95) WITHIN GROUP (ORDER BY latency_ms) FILTER (WHERE is_success AND latency_ms IS NOT NULL))::integer
            FROM check_results
            WHERE NOT in_maintenance
                AND started_at < date_bin('1 hour'::interval, now(), timestamptz '1970-01-01 00:00:00+00')
            GROUP BY 1, 2
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_hourly_stats');
    }
};
