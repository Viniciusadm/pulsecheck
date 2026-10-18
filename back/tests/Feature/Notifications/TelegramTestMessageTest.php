<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramTestMessageTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw_';

    private function configuredUser(): User
    {
        return NotificationSetting::factory()->create([
            'telegram_bot_token' => self::TOKEN,
            'telegram_chat_id' => '-100123',
            'telegram_enabled' => true,
        ])->user;
    }

    public function test_sends_a_test_message_with_the_saved_settings(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $this->actingAs($this->configuredUser())->fromSpa()
            ->postJson('/api/settings/notifications/telegram/test')
            ->assertOk()
            ->assertExactJson(['message' => 'Test message sent.']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.telegram.org/bot'.self::TOKEN.'/sendMessage'
            && $request['chat_id'] === '-100123'
            && str_contains($request['text'], 'PulseCheck'));
    }

    public function test_rejected_credentials_return_a_generic_error_without_the_raw_response(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized: internal detail'], 401)]);

        $response = $this->actingAs($this->configuredUser())->fromSpa()
            ->postJson('/api/settings/notifications/telegram/test');

        $response->assertUnprocessable()->assertExactJson(['message' => 'Telegram rejected the token or chat ID.']);
        $this->assertStringNotContainsString('internal detail', $response->getContent());
        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
    }

    public function test_unreachable_telegram_returns_a_generic_error(): void
    {
        Http::fake(['api.telegram.org/*' => Http::failedConnection()]);

        $response = $this->actingAs($this->configuredUser())->fromSpa()
            ->postJson('/api/settings/notifications/telegram/test');

        $response->assertStatus(503)->assertExactJson(['message' => 'Telegram could not be reached. Try again later.']);
        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
    }

    public function test_requires_saved_telegram_settings(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())->fromSpa()
            ->postJson('/api/settings/notifications/telegram/test')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('telegram');

        Http::assertNothingSent();
    }

    public function test_test_messages_are_rate_limited(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $this->actingAs($this->configuredUser())->fromSpa();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/settings/notifications/telegram/test')->assertOk();
        }

        $this->postJson('/api/settings/notifications/telegram/test')->assertTooManyRequests();
        Http::assertSentCount(5);
    }
}
