<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->string('type', 20)->default('http');
            $table->jsonb('options')->default('{}');
            $table->text('secrets')->nullable();
            $table->integer('latency_threshold_ms')->nullable();
            $table->unsignedInteger('config_revision')->default(1);
            $table->smallInteger('consecutive_degraded')->default(0);
            $table->smallInteger('consecutive_healthy')->default(0);
        });

        DB::statement("ALTER TABLE monitors ADD CONSTRAINT monitors_type_check CHECK (type IN ('http', 'keyword', 'ssl', 'tcp', 'heartbeat'))");
        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_latency_threshold_ms_check CHECK (latency_threshold_ms IS NULL OR latency_threshold_ms BETWEEN 1 AND 60000)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE monitors DROP CONSTRAINT monitors_type_check');
        DB::statement('ALTER TABLE monitors DROP CONSTRAINT monitors_latency_threshold_ms_check');

        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['type', 'options', 'secrets', 'latency_threshold_ms', 'config_revision', 'consecutive_degraded', 'consecutive_healthy']);
        });
    }
};
