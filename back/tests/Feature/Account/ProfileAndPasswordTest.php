<?php

namespace Tests\Feature\Account;

use App\Models\AuditLogEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileAndPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_updates_their_name_without_a_password(): void
    {
        $user = User::factory()->create(['name' => 'Ada']);

        $this->actingAs($user)->fromSpa()->putJson('/api/me/profile', ['name' => '  Ada Lovelace ', 'email' => $user->email])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ada Lovelace');

        $entry = AuditLogEntry::query()->where('action', 'user.updated')->sole();
        $this->assertSame(['fields' => ['name']], $entry->metadata);
        $this->assertSame($user->id, $entry->user_id);
    }

    public function test_changing_the_email_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-42']);
        User::factory()->create(['email' => 'taken@example.com']);
        $this->actingAs($user)->fromSpa();

        $this->putJson('/api/me/profile', ['name' => $user->name, 'email' => 'new@example.com'])
            ->assertJsonValidationErrors('current_password');
        $this->putJson('/api/me/profile', ['name' => $user->name, 'email' => 'taken@example.com', 'current_password' => 'secret-pass-42'])
            ->assertJsonValidationErrors('email');
        $this->putJson('/api/me/profile', ['name' => $user->name, 'email' => 'NEW@example.com', 'current_password' => 'secret-pass-42'])
            ->assertOk()
            ->assertJsonPath('data.email', 'new@example.com');
    }

    public function test_user_changes_their_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-42']);
        $this->actingAs($user)->fromSpa();

        $this->putJson('/api/me/password', ['current_password' => 'wrong', 'password' => 'brand-new-42', 'password_confirmation' => 'brand-new-42'])
            ->assertJsonValidationErrors('current_password');
        $this->putJson('/api/me/password', ['current_password' => 'secret-pass-42', 'password' => 'brand-new-42', 'password_confirmation' => 'other-42'])
            ->assertJsonValidationErrors('password');
        $this->putJson('/api/me/password', ['current_password' => 'secret-pass-42', 'password' => 'brand-new-42', 'password_confirmation' => 'brand-new-42'])
            ->assertNoContent();

        $this->assertTrue(Hash::check('brand-new-42', $user->fresh()->password));
        $this->assertTrue(AuditLogEntry::query()->where('action', 'account.password_changed')->exists());
        $this->assertFalse(AuditLogEntry::query()->where('action', 'user.updated')->exists());
    }

    public function test_changing_the_password_ends_other_sessions(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-pass-42']);

        $other = $this->fromSpa()->postJson('/api/auth/login', ['email' => 'ada@example.com', 'password' => 'secret-pass-42'])->assertOk();
        $otherSession = $this->sessionIdFrom($other);
        $this->useSession($otherSession)->fromSpa()->getJson('/api/user')->assertOk();

        $this->withoutSession();
        $this->actingAs($user)->fromSpa()->putJson('/api/me/password', [
            'current_password' => 'secret-pass-42',
            'password' => 'brand-new-42',
            'password_confirmation' => 'brand-new-42',
        ])->assertNoContent();

        $this->useSession($otherSession)->fromSpa()->getJson('/api/user')->assertUnauthorized();
    }

    public function test_failed_logins_are_audited_without_a_user(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->fromSpa()->postJson('/api/auth/login', ['email' => 'ada@example.com', 'password' => 'wrong'])->assertUnprocessable();

        $entry = AuditLogEntry::query()->sole();
        $this->assertSame('auth.login_failed', $entry->action);
        $this->assertNull($entry->user_id);
        $this->assertSame(['email' => 'ada@example.com'], $entry->metadata);
    }
}
