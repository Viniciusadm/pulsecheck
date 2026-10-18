<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('notification_settings')
            ->whereNotNull('telegram_bot_token')
            ->whereNotNull('telegram_chat_id')
            ->orderBy('id')
            ->each(function (object $setting): void {
                $now = now();

                $channelId = DB::table('notification_channels')->insertGetId([
                    'user_id' => $setting->user_id,
                    'type' => 'telegram',
                    'name' => 'Telegram',
                    'config' => Crypt::encryptString(json_encode([
                        'bot_token' => Crypt::decryptString($setting->telegram_bot_token),
                        'chat_id' => $setting->telegram_chat_id,
                    ])),
                    'enabled' => (bool) $setting->telegram_enabled,
                    'is_default' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('monitor_notification_channel')->insertUsing(
                    ['monitor_id', 'notification_channel_id'],
                    DB::table('monitors')->where('user_id', $setting->user_id)->selectRaw('id, ?', [$channelId]),
                );
            });

        Schema::drop('notification_settings');
    }

    public function down(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('telegram_bot_token')->nullable();
            $table->string('telegram_chat_id')->nullable();
            $table->boolean('telegram_enabled')->default(false);
            $table->timestamps();
        });
    }
};
