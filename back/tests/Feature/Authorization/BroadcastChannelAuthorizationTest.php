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

    private function authorize(int $userId): TestResponse
    {
        return $this->fromSpa()->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-users.{$userId}",
        ]);
    }

    public function test_user_can_subscribe_to_their_own_channel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->authorize($user->id)
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_user_cannot_subscribe_to_another_users_channel(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->authorize($other->id)->assertForbidden();
    }

    public function test_guest_cannot_subscribe(): void
    {
        $user = User::factory()->create();

        $this->authorize($user->id)->assertUnauthorized();
    }
}
