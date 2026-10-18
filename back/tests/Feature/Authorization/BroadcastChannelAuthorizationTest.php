<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BroadcastChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');
    }

    private function authorize(string $channel): TestResponse
    {
        return $this->fromSpa()->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channel,
        ]);
    }

    public function test_users_can_subscribe_to_their_own_monitors_channel(): void
    {
        $member = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($member)->authorize("private-users.{$member->id}.monitors")
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->actingAs($admin)->authorize("private-users.{$admin->id}.monitors")
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_users_cannot_subscribe_to_another_users_monitors_channel(): void
    {
        $member = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->authorize("private-users.{$member->id}.monitors")->assertForbidden();
    }

    public function test_unknown_channels_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->authorize('private-monitors')->assertForbidden();
        $this->actingAs($user)->authorize("private-users.{$user->id}")->assertForbidden();
    }

    public function test_guest_cannot_subscribe(): void
    {
        $user = User::factory()->create();

        $this->authorize("private-users.{$user->id}.monitors")->assertUnauthorized();
    }
}
