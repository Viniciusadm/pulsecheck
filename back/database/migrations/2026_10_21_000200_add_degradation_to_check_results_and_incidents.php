<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_results', function (Blueprint $table) {
            $table->boolean('is_degraded')->default(false);
            $table->string('warning_type')->nullable();
            $table->string('warning_message', 255)->nullable();
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->string('kind', 20)->default('outage');
        });

        Schema::table('monitor_daily_uptimes', function (Blueprint $table) {
            $table->unsignedInteger('degraded_checks')->default(0);
        });

        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_kind_check CHECK (kind IN ('outage', 'degradation'))");
        DB::statement('CREATE INDEX check_results_monitor_id_started_at_metrics_v2_index ON check_results (monitor_id, started_at) INCLUDE (is_success, latency_ms, error_type, is_degraded)');
        DB::statement('DROP INDEX check_results_monitor_id_started_at_metrics_index');
        DB::statement('ALTER INDEX check_results_monitor_id_started_at_metrics_v2_index RENAME TO check_results_monitor_id_started_at_metrics_index');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX check_results_monitor_id_started_at_metrics_index');
        DB::statement('CREATE INDEX check_results_monitor_id_started_at_metrics_index ON check_results (monitor_id, started_at) INCLUDE (is_success, latency_ms, error_type)');
        DB::statement('ALTER TABLE incidents DROP CONSTRAINT incidents_kind_check');

        Schema::table('monitor_daily_uptimes', function (Blueprint $table) {
            $table->dropColumn('degraded_checks');
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::table('check_results', function (Blueprint $table) {
            $table->dropColumn(['is_degraded', 'warning_type', 'warning_message']);
        });
    }
};
