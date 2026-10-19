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
            $table->string('url', 2048)->nullable()->change();
            $table->string('heartbeat_token', 64)->nullable()->unique();
            $table->timestampTz('last_heartbeat_at')->nullable();
            $table->timestampTz('certificate_expires_at')->nullable();
        });

        DB::statement("ALTER TABLE monitors ADD CONSTRAINT monitors_url_required_check CHECK (type IN ('tcp', 'heartbeat') OR url IS NOT NULL)");
        DB::statement("ALTER TABLE monitors ADD CONSTRAINT monitors_heartbeat_token_check CHECK (type <> 'heartbeat' OR heartbeat_token IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE monitors DROP CONSTRAINT monitors_url_required_check');
        DB::statement('ALTER TABLE monitors DROP CONSTRAINT monitors_heartbeat_token_check');
        DB::table('monitors')->whereNull('url')->delete();

        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['heartbeat_token', 'last_heartbeat_at', 'certificate_expires_at']);
            $table->string('url', 2048)->nullable(false)->change();
        });
    }
};
