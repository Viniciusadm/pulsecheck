<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('team_user', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['team_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('monitor_team', function (Blueprint $table) {
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->primary(['monitor_id', 'team_id']);
            $table->index('team_id');
        });

        Schema::create('notification_channel_team', function (Blueprint $table) {
            $table->foreignId('notification_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->primary(['notification_channel_id', 'team_id']);
            $table->index('team_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_view_all_monitors')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_view_all_monitors');
        });

        Schema::dropIfExists('notification_channel_team');
        Schema::dropIfExists('monitor_team');
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
    }
};
