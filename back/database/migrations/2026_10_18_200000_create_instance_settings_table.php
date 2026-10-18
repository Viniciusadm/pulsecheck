<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('timezone', 64);
            $table->string('locale', 10)->default('en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instance_settings');
    }
};
