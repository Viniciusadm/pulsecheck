<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->fromSpa()->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_email_is_case_insensitive(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->fromSpa()->postJson('/api/auth/login', [
            'email' => 'ADA@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_regenerates_the_session(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);
        $guestSessionId = $this->sessionIdFrom($this->fromSpa()->get('/sanctum/csrf-cookie'));
        $guestCsrfToken = session()->token();

        $loginResponse = $this->useSession($guestSessionId)->fromSpa()->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertNotSame($guestSessionId, $this->sessionIdFrom($loginResponse));
        $this->assertNotSame($guestCsrfToken, session()->token());
    }

    public function test_session_keeps_the_user_logged_in_across_requests(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $loginResponse = $this->fromSpa()->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->useSession($this->sessionIdFrom($loginResponse))->fromSpa()->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_wrong_password_and_unknown_email_get_the_same_generic_error(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $wrongPassword = $this->fromSpa()->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ]);
        $unknownEmail = $this->fromSpa()->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $wrongPassword->assertUnprocessable()->assertJsonValidationErrors('email');
        $unknownEmail->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame($wrongPassword->json('errors'), $unknownEmail->json('errors'));
        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->fromSpa()->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited_per_email_and_ip(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->fromSpa()->postJson('/api/auth/login', [
                'email' => 'ada@example.com',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->fromSpa()->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_login_rate_limit_for_one_email_does_not_block_another(): void
    {
        User::factory()->create(['email' => 'grace@example.com']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->fromSpa()->postJson('/api/auth/login', [
                'email' => 'ada@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->fromSpa()->postJson('/api/auth/login', [
            'email' => 'grace@example.com',
            'password' => 'password',
        ])->assertOk();
    }
}
