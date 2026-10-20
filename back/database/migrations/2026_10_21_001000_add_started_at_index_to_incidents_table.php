<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX incidents_started_at_id_index ON incidents (started_at DESC, id DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX incidents_started_at_id_index');
    }
};
