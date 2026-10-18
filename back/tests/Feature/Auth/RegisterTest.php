<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'correct-horse-42',
            'password_confirmation' => 'correct-horse-42',
        ], $overrides);
    }

    public function test_user_can_register_and_is_logged_in(): void
    {
        $response = $this->fromSpa()->postJson('/api/auth/register', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.email', 'ada@example.com')
            ->assertJsonPath('data.name', 'Ada Lovelace')
            ->assertJsonMissingPath('data.password');

        $user = User::firstWhere('email', 'ada@example.com');
        $this->assertTrue(Hash::check('correct-horse-42', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_email_is_normalized_to_lowercase(): void
    {
        $this->fromSpa()->postJson('/api/auth/register', $this->validPayload(['email' => 'Ada@Example.COM']))
            ->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->fromSpa()->postJson('/api/auth/register', $this->validPayload(['email' => 'ADA@example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->fromSpa()->postJson('/api/auth/register', $this->validPayload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->fromSpa()->postJson('/api/auth/register', $this->validPayload([
            'password_confirmation' => 'something-else-42',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_required_fields_are_validated(): void
    {
        $this->fromSpa()->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->fromSpa()->postJson('/api/auth/register', [])->assertUnprocessable();
        }

        $this->fromSpa()->postJson('/api/auth/register', [])->assertTooManyRequests();
    }
}
