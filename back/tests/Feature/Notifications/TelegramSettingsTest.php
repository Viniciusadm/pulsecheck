<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TelegramSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw_';

    public function test_settings_never_expose_the_token(): void
    {
        $setting = NotificationSetting::factory()->create([
            'telegram_bot_token' => self::TOKEN,
            'telegram_chat_id' => '-100123',
            'telegram_enabled' => true,
        ]);

        $response = $this->actingAs($setting->user)->fromSpa()->getJson('/api/settings/notifications');

        $response->assertOk()->assertExactJson(['data' => [
            'telegram_enabled' => true,
            'telegram_chat_id' => '-100123',
            'has_telegram_token' => true,
        ]]);
        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
    }

    public function test_user_without_settings_gets_defaults(): void
    {
        $this->actingAs(User::factory()->create())->fromSpa()
            ->getJson('/api/settings/notifications')
            ->assertOk()
            ->assertExactJson(['data' => [
                'telegram_enabled' => false,
                'telegram_chat_id' => null,
                'has_telegram_token' => false,
            ]]);
    }

    public function test_user_can_configure_and_enable_telegram(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->fromSpa()->putJson('/api/settings/notifications', [
            'telegram_bot_token' => self::TOKEN,
            'telegram_chat_id' => '@my_status_channel',
            'telegram_enabled' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.has_telegram_token', true)
            ->assertJsonPath('data.telegram_enabled', true)
            ->assertJsonPath('data.telegram_chat_id', '@my_status_channel');
        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
        $this->assertSame(self::TOKEN, $user->fresh()->notificationSetting->telegram_bot_token);
    }

    public function test_update_without_token_keeps_the_existing_token(): void
    {
        $setting = NotificationSetting::factory()->create(['telegram_bot_token' => self::TOKEN, 'telegram_chat_id' => '1']);

        $this->actingAs($setting->user)->fromSpa()->putJson('/api/settings/notifications', [
            'telegram_chat_id' => '987654',
            'telegram_bot_token' => '',
            'telegram_enabled' => true,
        ])->assertOk()->assertJsonPath('data.has_telegram_token', true);

        $setting->refresh();
        $this->assertSame(self::TOKEN, $setting->telegram_bot_token);
        $this->assertSame('987654', $setting->telegram_chat_id);
        $this->assertTrue($setting->telegram_enabled);
    }

    public function test_enabling_requires_a_token_and_a_chat_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->fromSpa()
            ->putJson('/api/settings/notifications', ['telegram_chat_id' => '123', 'telegram_enabled' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('telegram_enabled');

        $this->assertNull($user->fresh()->notificationSetting);
    }

    public static function invalidValues(): array
    {
        return [
            'token without bot id' => [['telegram_bot_token' => 'abc:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw_'], 'telegram_bot_token'],
            'token too short' => [['telegram_bot_token' => '123:short'], 'telegram_bot_token'],
            'token with spaces' => [['telegram_bot_token' => '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5 PALDsaw'], 'telegram_bot_token'],
            'chat id with letters' => [['telegram_chat_id' => '12ab'], 'telegram_chat_id'],
            'channel too short' => [['telegram_chat_id' => '@abc'], 'telegram_chat_id'],
            'enabled not boolean' => [['telegram_enabled' => 'maybe'], 'telegram_enabled'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected(array $payload, string $field): void
    {
        $this->actingAs(User::factory()->create())->fromSpa()
            ->putJson('/api/settings/notifications', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_telegram_can_be_removed(): void
    {
        $setting = NotificationSetting::factory()->withTelegram()->create();

        $this->actingAs($setting->user)->fromSpa()
            ->deleteJson('/api/settings/notifications/telegram')
            ->assertOk()
            ->assertExactJson(['data' => [
                'telegram_enabled' => false,
                'telegram_chat_id' => null,
                'has_telegram_token' => false,
            ]]);

        $setting->refresh();
        $this->assertNull($setting->telegram_bot_token);
        $this->assertNull($setting->telegram_chat_id);
        $this->assertFalse($setting->telegram_enabled);
    }

    public function test_settings_belong_to_the_authenticated_user_only(): void
    {
        $other = NotificationSetting::factory()->withTelegram()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->fromSpa()->putJson('/api/settings/notifications', ['telegram_chat_id' => '42'])->assertOk();
        $this->actingAs($user)->fromSpa()->deleteJson('/api/settings/notifications/telegram')->assertOk();

        $this->assertNotNull($other->fresh()->telegram_bot_token);
        $this->assertNotSame('42', $other->fresh()->telegram_chat_id);
    }
}
