<?php

namespace Tests\Feature\Models;

use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationSettingTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '123456789:AAExampleBotTokenValue';

    public function test_telegram_bot_token_is_encrypted_at_rest(): void
    {
        $setting = NotificationSetting::factory()->create(['telegram_bot_token' => self::TOKEN]);

        $storedValue = DB::table('notification_settings')->where('id', $setting->id)->value('telegram_bot_token');

        $this->assertNotSame(self::TOKEN, $storedValue);
        $this->assertStringNotContainsString(self::TOKEN, $storedValue);
        $this->assertSame(self::TOKEN, $setting->fresh()->telegram_bot_token);
    }

    public function test_telegram_bot_token_is_hidden_from_serialization(): void
    {
        $setting = NotificationSetting::factory()->create(['telegram_bot_token' => self::TOKEN]);

        $this->assertArrayNotHasKey('telegram_bot_token', $setting->fresh()->toArray());
        $this->assertStringNotContainsString(self::TOKEN, $setting->fresh()->toJson());
    }

    public function test_user_has_at_most_one_notification_setting(): void
    {
        $user = User::factory()->create();
        NotificationSetting::factory()->for($user)->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23505');

        NotificationSetting::factory()->for($user)->create();
    }

    public function test_user_relationship_returns_the_setting(): void
    {
        $setting = NotificationSetting::factory()->create();

        $this->assertTrue($setting->user->notificationSetting->is($setting));
    }
}
