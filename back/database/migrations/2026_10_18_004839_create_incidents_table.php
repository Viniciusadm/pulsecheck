<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->string('cause', 500);
            $table->timestamps();

            $table->index(['monitor_id', 'started_at']);
        });

        DB::statement('CREATE UNIQUE INDEX incidents_one_open_per_monitor ON incidents (monitor_id) WHERE resolved_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
