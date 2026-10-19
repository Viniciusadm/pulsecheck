<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->timestampTz('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('last_reminded_at')->nullable();
            $table->unsignedSmallInteger('reminder_count')->default(0);
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->unsignedSmallInteger('reminder_interval_minutes')->nullable();
        });

        Schema::create('incident_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['incident_id', 'created_at']);
        });

        DB::statement('CREATE INDEX incidents_open_unacknowledged_index ON incidents (monitor_id) WHERE resolved_at IS NULL AND acknowledged_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX incidents_open_unacknowledged_index');

        Schema::dropIfExists('incident_notes');

        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn('reminder_interval_minutes');
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acknowledged_by');
            $table->dropColumn(['acknowledged_at', 'last_reminded_at', 'reminder_count']);
        });
    }
};
