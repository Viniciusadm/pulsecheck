<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX check_results_monitor_id_started_at_metrics_index ON check_results (monitor_id, started_at) INCLUDE (is_success, latency_ms, error_type)');
        DB::statement('DROP INDEX check_results_monitor_id_started_at_index');
    }

    public function down(): void
    {
        DB::statement('CREATE INDEX check_results_monitor_id_started_at_index ON check_results (monitor_id, started_at)');
        DB::statement('DROP INDEX check_results_monitor_id_started_at_metrics_index');
    }
};
