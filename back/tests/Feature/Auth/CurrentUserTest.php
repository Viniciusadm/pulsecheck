<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_unauthorized_json(): void
    {
        $this->fromSpa()->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_authenticated_user_gets_their_profile_without_sensitive_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->fromSpa()->getJson('/api/user');

        $response->assertOk()
            ->assertExactJsonStructure(['data' => ['id', 'name', 'email', 'email_verified_at', 'created_at']])
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_api_errors_are_json_without_stack_traces(): void
    {
        config(['app.debug' => false]);

        $this->fromSpa()->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertJsonMissingPath('trace')
            ->assertJsonMissingPath('exception')
            ->assertJsonStructure(['message']);
    }
}
