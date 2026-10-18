<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('name', 100);
            $table->text('config');
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('monitor_notification_channel', function (Blueprint $table) {
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_channel_id')->constrained()->cascadeOnDelete();
            $table->primary(['monitor_id', 'notification_channel_id']);
            $table->index('notification_channel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_notification_channel');
        Schema::dropIfExists('notification_channels');
    }
};
