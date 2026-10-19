<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_pages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 60)->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('show_latency')->default(false);
            $table->boolean('show_incidents')->default(true);
            $table->smallInteger('uptime_days')->default(90);
            $table->timestamps();
        });

        Schema::create('monitor_status_page', function (Blueprint $table) {
            $table->foreignId('status_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('display_name', 100)->nullable();

            $table->primary(['status_page_id', 'monitor_id']);
            $table->index('monitor_id');
        });

        Schema::create('status_page_team', function (Blueprint $table) {
            $table->foreignId('status_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->primary(['status_page_id', 'team_id']);
            $table->index('team_id');
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->string('badge_token', 40)->nullable()->unique();
        });

        DB::statement('ALTER TABLE status_pages ADD CONSTRAINT status_pages_uptime_days_check CHECK (uptime_days IN (30, 60, 90))');
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn('badge_token');
        });

        Schema::dropIfExists('status_page_team');
        Schema::dropIfExists('monitor_status_page');
        Schema::dropIfExists('status_pages');
    }
};
