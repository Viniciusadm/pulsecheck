<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at');
            $table->integer('latency_ms')->nullable();
            $table->smallInteger('status_code')->nullable();
            $table->boolean('is_success');
            $table->string('error_type')->nullable();
            $table->string('error_message', 500)->nullable();

            $table->index(['monitor_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_results');
    }
};
