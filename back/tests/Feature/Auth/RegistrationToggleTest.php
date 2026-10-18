<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_can_be_disabled(): void
    {
        config(['pulsecheck.registration_enabled' => false]);

        $this->fromSpa()->postJson('/api/auth/register', [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'correct-horse-42',
            'password_confirmation' => 'correct-horse-42',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Registration is disabled on this PulseCheck instance.');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_public_config_tells_whether_registration_is_open(): void
    {
        config(['pulsecheck.registration_enabled' => false]);
        $this->fromSpa()->getJson('/api/config')->assertJsonPath('data.registration_enabled', false);

        config(['pulsecheck.registration_enabled' => true]);
        $this->fromSpa()->getJson('/api/config')->assertJsonPath('data.registration_enabled', true);
    }
}
