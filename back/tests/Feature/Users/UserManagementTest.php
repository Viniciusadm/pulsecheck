<?php

namespace Tests\Feature\Users;

use App\Actions\DeleteUser;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_cannot_manage_users(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($member)->fromSpa();

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', [])->assertForbidden();
        $this->putJson("/api/users/{$other->id}", ['name' => 'x'])->assertForbidden();
        $this->deleteJson("/api/users/{$other->id}")->assertForbidden();
        $this->putJson('/api/instance', ['name' => 'x', 'timezone' => 'UTC'])->assertForbidden();

        $this->assertModelExists($other);
    }

    public function test_admin_lists_users_ordered_by_name(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Bruno']);
        $member = User::factory()->create(['name' => 'Alice']);

        $this->actingAs($admin)->fromSpa()->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $member->id)
            ->assertJsonPath('data.0.role', 'member')
            ->assertJsonPath('data.1.id', $admin->id)
            ->assertJsonPath('data.1.role', 'admin')
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_admin_creates_a_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->fromSpa()->postJson('/api/users', [
            'name' => 'Grace Hopper',
            'email' => 'GRACE@example.com',
            'password' => 'initial-pass-42',
            'password_confirmation' => 'initial-pass-42',
            'role' => 'member',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'grace@example.com')
            ->assertJsonPath('data.role', 'member');

        $user = User::query()->where('email', 'grace@example.com')->sole();
        $this->assertSame(UserRole::Member, $user->role);
        $this->assertTrue(Hash::check('initial-pass-42', $user->password));
    }

    public static function invalidStorePayloads(): array
    {
        return [
            'missing name' => [['name' => null], 'name'],
            'invalid email' => [['email' => 'nope'], 'email'],
            'duplicate email' => [['email' => 'taken@example.com'], 'email'],
            'invalid role' => [['role' => 'owner'], 'role'],
            'missing password' => [['password' => null], 'password'],
            'unconfirmed password' => [['password_confirmation' => 'other-pass-42'], 'password'],
        ];
    }

    #[DataProvider('invalidStorePayloads')]
    public function test_store_validates_the_payload(array $overrides, string $field): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)->fromSpa()->postJson('/api/users', array_merge([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'password' => 'initial-pass-42',
            'password_confirmation' => 'initial-pass-42',
            'role' => 'member',
        ], $overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_admin_updates_a_user_without_changing_the_password(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $originalHash = $member->password;

        $this->actingAs($admin)->fromSpa()->putJson("/api/users/{$member->id}", [
            'name' => 'Renamed',
            'email' => 'renamed@example.com',
            'role' => 'admin',
            'password' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.role', 'admin');

        $member->refresh();
        $this->assertSame('renamed@example.com', $member->email);
        $this->assertSame(UserRole::Admin, $member->role);
        $this->assertSame($originalHash, $member->password);
    }

    public function test_admin_sets_a_new_password(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->fromSpa()->patchJson("/api/users/{$member->id}", [
            'password' => 'brand-new-pass-42',
            'password_confirmation' => 'brand-new-pass-42',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-pass-42', $member->fresh()->password));
    }

    public function test_update_keeps_the_users_own_email_unique_check(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)->fromSpa()->putJson("/api/users/{$member->id}", ['name' => 'Same', 'email' => 'member@example.com', 'role' => 'member'])
            ->assertOk();

        $this->actingAs($admin)->fromSpa()->putJson("/api/users/{$member->id}", ['name' => 'Same', 'email' => 'taken@example.com', 'role' => 'member'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_the_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->fromSpa()->putJson("/api/users/{$admin->id}", ['name' => $admin->name, 'email' => $admin->email, 'role' => 'member'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_an_admin_can_be_demoted_when_another_admin_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($admin)->fromSpa()->patchJson("/api/users/{$otherAdmin->id}", ['role' => 'member'])
            ->assertOk()
            ->assertJsonPath('data.role', 'member');
    }

    public function test_admin_deletes_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->fromSpa()->deleteJson("/api/users/{$member->id}")->assertNoContent();

        $this->assertModelMissing($member);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->actingAs($admin)->fromSpa()->deleteJson("/api/users/{$admin->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');

        $this->assertModelExists($admin);
    }

    public function test_the_last_admin_cannot_be_deleted(): void
    {
        $lastAdmin = User::factory()->admin()->create();
        $actor = User::factory()->create();

        try {
            app(DeleteUser::class)->handle($actor, $lastAdmin);
            $this->fail('The last admin was deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertModelExists($lastAdmin);
    }

    public function test_unknown_users_are_not_found(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->fromSpa()->deleteJson('/api/users/999999')->assertNotFound();
    }
}
