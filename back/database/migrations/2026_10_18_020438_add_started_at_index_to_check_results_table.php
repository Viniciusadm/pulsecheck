<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_results', function (Blueprint $table) {
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('check_results', function (Blueprint $table) {
            $table->dropIndex(['started_at']);
        });
    }
};
