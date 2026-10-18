<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('url', 2048);
            $table->smallInteger('interval_minutes');
            $table->smallInteger('timeout_seconds');
            $table->string('status')->default('pending');
            $table->boolean('is_paused')->default(false);
            $table->smallInteger('consecutive_failures')->default(0);
            $table->smallInteger('consecutive_successes')->default(0);
            $table->smallInteger('last_status_code')->nullable();
            $table->integer('last_latency_ms')->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampTz('last_failed_at')->nullable();
            $table->timestampTz('next_check_at')->nullable();
            $table->timestamps();

            $table->index(['is_paused', 'next_check_at']);
        });

        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_interval_minutes_check CHECK (interval_minutes IN (1, 5, 10))');
        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_timeout_seconds_check CHECK (timeout_seconds BETWEEN 1 AND 30)');
    }

    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
