<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_a_reset_link_pointing_to_the_spa(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://pulsecheck.test']);
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->fromSpa()->postJson('/api/auth/forgot-password', ['email' => 'ada@example.com'])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'https://pulsecheck.test/reset-password?')
                && str_contains($url, 'token='.$notification->token)
                && str_contains($url, 'email='.urlencode('ada@example.com'));
        });
    }

    public function test_forgot_password_responds_the_same_for_unknown_email(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'ada@example.com']);

        $known = $this->fromSpa()->postJson('/api/auth/forgot-password', ['email' => 'ada@example.com']);
        $unknown = $this->fromSpa()->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_forgot_password_validates_email_format(): void
    {
        $this->fromSpa()->postJson('/api/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        Notification::fake();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->fromSpa()->postJson('/api/auth/forgot-password', ['email' => "user{$attempt}@example.com"])->assertOk();
        }

        $this->fromSpa()->postJson('/api/auth/forgot-password', ['email' => 'user6@example.com'])->assertTooManyRequests();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $token = Password::createToken($user);

        $this->fromSpa()->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'ada@example.com',
            'password' => 'new-password-42',
            'password_confirmation' => 'new-password-42',
        ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertTrue(Hash::check('new-password-42', $user->fresh()->password));
        $this->assertGuest();
    }

    public function test_reset_with_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->fromSpa()->postJson('/api/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => 'ada@example.com',
            'password' => 'new-password-42',
            'password_confirmation' => 'new-password-42',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_token_cannot_be_used_twice(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $token = Password::createToken($user);
        $payload = [
            'token' => $token,
            'email' => 'ada@example.com',
            'password' => 'new-password-42',
            'password_confirmation' => 'new-password-42',
        ];

        $this->fromSpa()->postJson('/api/auth/reset-password', $payload)->assertOk();

        $this->fromSpa()->postJson('/api/auth/reset-password', [...$payload, 'password' => 'another-pass-42', 'password_confirmation' => 'another-pass-42'])
            ->assertUnprocessable();
    }

    public function test_reset_rejects_weak_password(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->fromSpa()->postJson('/api/auth/reset-password', [
            'token' => Password::createToken($user),
            'email' => 'ada@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_existing_sessions_stop_working_after_password_reset(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $loginResponse = $this->fromSpa()->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])->assertOk();
        $sessionId = $this->sessionIdFrom($loginResponse);
        $this->useSession($sessionId)->fromSpa()->getJson('/api/user')->assertOk();

        $this->withoutSession()->fromSpa()->postJson('/api/auth/reset-password', [
            'token' => Password::createToken($user),
            'email' => 'ada@example.com',
            'password' => 'new-password-42',
            'password_confirmation' => 'new-password-42',
        ])->assertOk();

        $this->useSession($sessionId)->fromSpa()->getJson('/api/user')->assertUnauthorized();
    }
}
