<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_windows', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 20);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->jsonb('weekdays')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'ends_at']);
        });

        Schema::create('maintenance_window_monitor', function (Blueprint $table) {
            $table->foreignId('maintenance_window_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();

            $table->primary(['maintenance_window_id', 'monitor_id']);
            $table->index('monitor_id');
        });

        Schema::create('maintenance_window_team', function (Blueprint $table) {
            $table->foreignId('maintenance_window_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->primary(['maintenance_window_id', 'team_id']);
            $table->index('team_id');
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->timestampTz('maintenance_ends_at')->nullable();
        });

        Schema::table('check_results', function (Blueprint $table) {
            $table->boolean('in_maintenance')->default(false);
        });

        Schema::table('monitor_daily_uptimes', function (Blueprint $table) {
            $table->unsignedInteger('maintenance_checks')->default(0);
        });

        DB::statement("ALTER TABLE maintenance_windows ADD CONSTRAINT maintenance_windows_type_check CHECK (type IN ('one_off', 'recurring'))");
        DB::statement('CREATE INDEX check_results_monitor_id_started_at_metrics_v3_index ON check_results (monitor_id, started_at) INCLUDE (is_success, latency_ms, error_type, is_degraded, in_maintenance)');
        DB::statement('DROP INDEX check_results_monitor_id_started_at_metrics_index');
        DB::statement('ALTER INDEX check_results_monitor_id_started_at_metrics_v3_index RENAME TO check_results_monitor_id_started_at_metrics_index');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX check_results_monitor_id_started_at_metrics_index');
        DB::statement('CREATE INDEX check_results_monitor_id_started_at_metrics_index ON check_results (monitor_id, started_at) INCLUDE (is_success, latency_ms, error_type, is_degraded)');

        Schema::table('monitor_daily_uptimes', function (Blueprint $table) {
            $table->dropColumn('maintenance_checks');
        });

        Schema::table('check_results', function (Blueprint $table) {
            $table->dropColumn('in_maintenance');
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn('maintenance_ends_at');
        });

        Schema::dropIfExists('maintenance_window_team');
        Schema::dropIfExists('maintenance_window_monitor');
        Schema::dropIfExists('maintenance_windows');
    }
};
