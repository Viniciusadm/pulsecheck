<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannelType;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TelegramSettingsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_10_18_100001_move_telegram_settings_to_notification_channels.php';

    private const TOKEN = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw_';

    public function test_existing_telegram_settings_become_a_default_channel_attached_to_every_monitor(): void
    {
        $migration = require base_path(self::MIGRATION);
        $migration->down();

        $configured = User::factory()->create();
        $disabled = User::factory()->create();
        $empty = User::factory()->create();
        $monitors = Monitor::factory()->for($configured)->count(2)->create();
        $otherMonitor = Monitor::factory()->for($empty)->create();

        DB::table('notification_settings')->insert([
            ['user_id' => $configured->id, 'telegram_bot_token' => Crypt::encryptString(self::TOKEN), 'telegram_chat_id' => '-100123', 'telegram_enabled' => true],
            ['user_id' => $disabled->id, 'telegram_bot_token' => Crypt::encryptString(self::TOKEN), 'telegram_chat_id' => '42', 'telegram_enabled' => false],
            ['user_id' => $empty->id, 'telegram_bot_token' => null, 'telegram_chat_id' => null, 'telegram_enabled' => false],
        ]);

        $migration->up();

        $this->assertFalse(DB::getSchemaBuilder()->hasTable('notification_settings'));

        $channel = $configured->notificationChannels()->sole();
        $this->assertSame(NotificationChannelType::Telegram, $channel->type);
        $this->assertSame(['bot_token' => self::TOKEN, 'chat_id' => '-100123'], $channel->config);
        $this->assertTrue($channel->enabled);
        $this->assertTrue($channel->is_default);
        $this->assertEqualsCanonicalizing($monitors->modelKeys(), $channel->monitors()->pluck('monitors.id')->all());

        $this->assertFalse($disabled->notificationChannels()->sole()->enabled);
        $this->assertSame(0, $empty->notificationChannels()->count());
        $this->assertSame(0, $otherMonitor->notificationChannels()->count());
    }
}
