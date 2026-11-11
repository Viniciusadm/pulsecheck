<?php

namespace Tests\Feature\Account;

use App\Models\AuditLogEntry;
use App\Models\User;
use App\Security\Totp;
use App\Services\Auth\TwoFactorAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function currentCode(User $user, int $offsetSteps = 0): string
    {
        $totp = new Totp;

        return $totp->codeAt($user->fresh()->two_factor_secret, $totp->currentStep() + $offsetSteps);
    }

    private function userWithTwoFactor(): array
    {
        $user = User::factory()->create(['password' => 'secret-pass-42']);
        $service = app(TwoFactorAuthentication::class);
        $service->begin($user);
        $codes = $service->confirm($user, $this->currentCode($user, -1));

        return [$user->fresh(), $codes];
    }

    public function test_enabling_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-42']);

        $this->actingAs($user)->fromSpa()->postJson('/api/me/two-factor', ['current_password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_user_enables_and_confirms_two_factor(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-42']);
        $this->actingAs($user)->fromSpa();

        $this->postJson('/api/me/two-factor', ['current_password' => 'secret-pass-42'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'otpauth_url', 'qr_code_svg']]);

        $this->getJson('/api/me/two-factor')
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.pending_confirmation', true);

        $this->postJson('/api/me/two-factor/confirm', ['code' => '000000'])->assertJsonValidationErrors('code');

        $response = $this->postJson('/api/me/two-factor/confirm', ['code' => $this->currentCode($user)])
            ->assertOk()
            ->assertJsonCount(8, 'data.recovery_codes');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertNotContains($response->json('data.recovery_codes.0'), $user->fresh()->two_factor_recovery_codes);
        $this->getJson('/api/user')->assertJsonPath('data.two_factor_enabled', true);
        $this->getJson('/api/me/two-factor')->assertJsonPath('data.recovery_codes_remaining', 8);
        $this->assertTrue(AuditLogEntry::query()->where('action', 'account.two_factor_enabled')->exists());
    }

    public function test_login_with_two_factor_requires_a_code(): void
    {
        [$user] = $this->userWithTwoFactor();

        $login = $this->fromSpa()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret-pass-42'])
            ->assertOk()
            ->assertExactJson(['two_factor' => true]);

        $session = $this->sessionIdFrom($login);
        $this->useSession($session)->fromSpa()->getJson('/api/user')->assertUnauthorized();

        $this->useSession($session)->fromSpa()->postJson('/api/auth/two-factor-challenge', ['code' => '123456'])
            ->assertJsonValidationErrors('code');

        $challenge = $this->useSession($session)->fromSpa()->postJson('/api/auth/two-factor-challenge', ['code' => $this->currentCode($user)])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->useSession($this->sessionIdFrom($challenge))->fromSpa()->getJson('/api/user')->assertOk();
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        [$user] = $this->userWithTwoFactor();
        $code = $this->currentCode($user);

        $first = $this->fromSpa()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret-pass-42']);
        $this->useSession($this->sessionIdFrom($first))->fromSpa()->postJson('/api/auth/two-factor-challenge', ['code' => $code])->assertOk();

        $this->withoutSession();
        $second = $this->fromSpa()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret-pass-42']);
        $this->useSession($this->sessionIdFrom($second))->fromSpa()->postJson('/api/auth/two-factor-challenge', ['code' => $code])
            ->assertJsonValidationErrors('code');
    }

    public function test_recovery_code_logs_in_once(): void
    {
        [$user, $codes] = $this->userWithTwoFactor();

        $login = $this->fromSpa()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret-pass-42']);
        $this->useSession($this->sessionIdFrom($login))->fromSpa()->postJson('/api/auth/two-factor-challenge', ['recovery_code' => strtoupper($codes[0])])
            ->assertOk();

        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);

        $this->withoutSession();
        $again = $this->fromSpa()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret-pass-42']);
        $this->useSession($this->sessionIdFrom($again))->fromSpa()->postJson('/api/auth/two-factor-challenge', ['recovery_code' => $codes[0]])
            ->assertJsonValidationErrors('recovery_code');
    }

    public function test_challenge_without_a_pending_login_fails(): void
    {
        $this->fromSpa()->postJson('/api/auth/two-factor-challenge', ['code' => '123456'])
            ->assertJsonValidationErrors('code');
    }

    public function test_pending_login_expires(): void
    {
        [$user] = $this->userWithTwoFactor();

        $login = $this->fromSpa()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret-pass-42']);
        $this->travel(6)->minutes();

        $this->useSession($this->sessionIdFrom($login))->fromSpa()->postJson('/api/auth/two-factor-challenge', ['code' => $this->currentCode($user)])
            ->assertJsonValidationErrors('code');
    }

    public function test_user_disables_two_factor_and_regenerates_codes(): void
    {
        [$user, $codes] = $this->userWithTwoFactor();
        $this->actingAs($user)->fromSpa();

        $newCodes = $this->postJson('/api/me/two-factor/recovery-codes', ['current_password' => 'secret-pass-42'])
            ->assertOk()
            ->json('data.recovery_codes');
        $this->assertNotEquals($codes, $newCodes);

        $this->deleteJson('/api/me/two-factor', ['current_password' => 'nope'])->assertJsonValidationErrors('current_password');
        $this->deleteJson('/api/me/two-factor', ['current_password' => 'secret-pass-42'])->assertNoContent();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_admin_resets_another_users_two_factor(): void
    {
        [$user] = $this->userWithTwoFactor();
        $admin = User::factory()->admin()->create();

        $this->actingAs($user)->fromSpa()->deleteJson("/api/users/{$user->id}/two-factor")->assertForbidden();
        $this->actingAs($admin)->fromSpa()->deleteJson("/api/users/{$user->id}/two-factor")->assertNoContent();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertTrue(AuditLogEntry::query()->where('action', 'user.two_factor_reset')->where('subject_id', $user->id)->exists());
    }
}
