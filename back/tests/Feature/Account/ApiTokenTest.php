<?php

namespace Tests\Feature\Account;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json']);
    }

    public function test_user_creates_lists_and_revokes_tokens(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->fromSpa();

        $response = $this->postJson('/api/me/tokens', ['name' => 'CI', 'abilities' => ['read'], 'expires_in_days' => 30])
            ->assertCreated()
            ->assertJsonPath('data.name', 'CI')
            ->assertJsonPath('data.abilities', ['read']);

        $this->assertMatchesRegularExpression('/^\\d+\\|pulse_[A-Za-z0-9]{40,}$/', $response->json('plain_text_token'));
        $this->assertNotNull($response->json('data.expires_at'));

        $this->getJson('/api/me/tokens')->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.token');

        $tokenId = $response->json('data.id');
        $this->deleteJson("/api/me/tokens/{$tokenId}")->assertNoContent();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_token_validation(): void
    {
        $this->actingAs(User::factory()->create())->fromSpa();

        $this->postJson('/api/me/tokens', ['name' => '', 'abilities' => []])->assertJsonValidationErrors(['name', 'abilities']);
        $this->postJson('/api/me/tokens', ['name' => 'x', 'abilities' => ['admin']])->assertJsonValidationErrors('abilities.0');
        $this->postJson('/api/me/tokens', ['name' => 'x', 'abilities' => ['read'], 'expires_in_days' => 3])->assertJsonValidationErrors('expires_in_days');
    }

    public function test_users_cannot_revoke_tokens_of_others(): void
    {
        $owner = User::factory()->create();
        $token = $owner->createToken('mine', ['read']);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->deleteJson("/api/me/tokens/{$token->accessToken->id}")
            ->assertNotFound();

        $this->assertSame(1, $owner->tokens()->count());
    }

    public function test_read_token_can_read_but_not_write(): void
    {
        $user = User::factory()->manager()->create(['can_view_all_monitors' => true]);
        $monitor = Monitor::factory()->create();
        $token = $user->createToken('read', ['read'])->plainTextToken;

        $this->bearer($token)->getJson('/api/monitors')->assertOk()->assertJsonPath('data.0.id', $monitor->id);
        $this->bearer($token)->postJson("/api/monitors/{$monitor->id}/pause")->assertForbidden();
        $this->assertFalse($monitor->fresh()->is_paused);
    }

    public function test_write_token_can_change_data_within_the_users_role(): void
    {
        $user = User::factory()->manager()->create(['can_view_all_monitors' => true]);
        $monitor = Monitor::factory()->create();
        $token = $user->createToken('write', ['read', 'write'])->plainTextToken;

        $this->bearer($token)->postJson("/api/monitors/{$monitor->id}/pause")->assertOk();
        $this->assertTrue($monitor->fresh()->is_paused);
        $this->bearer($token)->getJson('/api/users')->assertForbidden();
    }

    public function test_tokens_cannot_manage_the_account(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-42']);
        $token = $user->createToken('write', ['read', 'write'])->plainTextToken;

        $this->bearer($token)->getJson('/api/me/tokens')->assertForbidden();
        $this->bearer($token)->postJson('/api/me/tokens', ['name' => 'x', 'abilities' => ['read']])->assertForbidden();
        $this->bearer($token)->putJson('/api/me/password', ['current_password' => 'secret-pass-42', 'password' => 'brand-new-42', 'password_confirmation' => 'brand-new-42'])->assertForbidden();
        $this->bearer($token)->deleteJson('/api/me/two-factor', ['current_password' => 'secret-pass-42'])->assertForbidden();
    }

    public function test_expired_tokens_are_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('old', ['read'], now()->subMinute())->plainTextToken;

        $this->bearer($token)->getJson('/api/monitors')->assertUnauthorized();
    }
}
