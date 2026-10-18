<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_ends_the_session(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);
        $loginResponse = $this->fromSpa()->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])->assertOk();
        $sessionId = $this->sessionIdFrom($loginResponse);
        $this->useSession($sessionId)->fromSpa()->getJson('/api/user')->assertOk();
        $csrfTokenBeforeLogout = session()->token();

        $this->useSession($sessionId)->fromSpa()->postJson('/api/auth/logout')->assertNoContent();

        $this->assertNotSame($csrfTokenBeforeLogout, session()->token());
        $this->useSession($sessionId)->fromSpa()->getJson('/api/user')->assertUnauthorized();
    }

    public function test_guest_cannot_log_out(): void
    {
        $this->fromSpa()->postJson('/api/auth/logout')->assertUnauthorized();
    }
}
