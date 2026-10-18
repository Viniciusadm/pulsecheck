<?php

namespace Tests\Feature\Authorization;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonitorPolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public static function abilities(): array
    {
        return [['view'], ['update'], ['delete'], ['pause'], ['resume']];
    }

    #[DataProvider('abilities')]
    public function test_owner_is_allowed(string $ability): void
    {
        $monitor = Monitor::factory()->create();

        $this->assertTrue(Gate::forUser($monitor->user)->inspect($ability, $monitor)->allowed());
    }

    #[DataProvider('abilities')]
    public function test_other_users_are_denied_as_not_found(string $ability): void
    {
        $monitor = Monitor::factory()->create();

        $response = Gate::forUser(User::factory()->create())->inspect($ability, $monitor);

        $this->assertTrue($response->denied());
        $this->assertSame(404, $response->status());
    }
}
