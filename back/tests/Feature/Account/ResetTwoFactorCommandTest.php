<?php

namespace Tests\Feature\Account;

use App\Models\AuditLogEntry;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetTwoFactorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_turns_off_two_factor_for_a_locked_out_account(): void
    {
        $user = User::factory()->admin()->create(['email' => 'ada@example.com']);
        app(TwoFactorAuthentication::class)->begin($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $this->artisan('pulsecheck:reset-two-factor', ['email' => ' ADA@example.com '])
            ->expectsOutputToContain('Two-factor authentication is off for ada@example.com')
            ->assertSuccessful();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertSame('Console', AuditLogEntry::query()->where('action', 'user.two_factor_reset')->sole()->actor_name);
    }

    public function test_it_reports_unknown_accounts(): void
    {
        $this->artisan('pulsecheck:reset-two-factor', ['email' => 'nobody@example.com'])->assertFailed();
    }
}
