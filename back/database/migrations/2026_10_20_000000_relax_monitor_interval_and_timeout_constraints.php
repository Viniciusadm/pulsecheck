<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE monitors DROP CONSTRAINT monitors_interval_minutes_check');
        DB::statement('ALTER TABLE monitors DROP CONSTRAINT monitors_timeout_seconds_check');
        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_interval_minutes_check CHECK (interval_minutes BETWEEN 1 AND 1440)');
        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_timeout_seconds_check CHECK (timeout_seconds BETWEEN 1 AND 60)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE monitors DROP CONSTRAINT monitors_interval_minutes_check');
        DB::statement('ALTER TABLE monitors DROP CONSTRAINT monitors_timeout_seconds_check');
        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_interval_minutes_check CHECK (interval_minutes IN (1, 5, 10))');
        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_timeout_seconds_check CHECK (timeout_seconds BETWEEN 1 AND 30)');
    }
};
