<?php

namespace Tests\Feature\Authorization;

use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonitorIsolationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public static function monitorRoutes(): array
    {
        return [
            'view' => ['GET', 'api/monitors/{monitor}'],
            'update' => ['PUT', 'api/monitors/{monitor}'],
            'delete' => ['DELETE', 'api/monitors/{monitor}'],
            'pause' => ['POST', 'api/monitors/{monitor}/pause'],
            'resume' => ['POST', 'api/monitors/{monitor}/resume'],
            'list checks' => ['GET', 'api/monitors/{monitor}/checks'],
            'list incidents' => ['GET', 'api/monitors/{monitor}/incidents'],
            'stats' => ['GET', 'api/monitors/{monitor}/stats'],
            'latency' => ['GET', 'api/monitors/{monitor}/latency'],
            'uptime history' => ['GET', 'api/monitors/{monitor}/uptime-history'],
        ];
    }

    #[DataProvider('monitorRoutes')]
    public function test_other_users_monitor_is_not_found_and_left_untouched(string $method, string $uri): void
    {
        $owner = User::factory()->create();
        $monitor = Monitor::factory()->for($owner)->paused()->create(['next_check_at' => null]);
        CheckResult::factory()->for($monitor)->create();
        Incident::factory()->for($monitor)->create();
        $snapshot = $monitor->fresh()->toArray();

        $response = $this->actingAs(User::factory()->create())
            ->fromSpa()
            ->json($method, '/'.str_replace('{monitor}', (string) $monitor->id, $uri));

        $response->assertNotFound();
        $this->assertStringNotContainsString($monitor->name, $response->getContent());
        $this->assertSame($snapshot, $monitor->fresh()->toArray());
    }

    #[DataProvider('monitorRoutes')]
    public function test_owner_can_access_the_route(string $method, string $uri): void
    {
        $owner = User::factory()->create();
        $monitor = Monitor::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->fromSpa()
            ->json($method, '/'.str_replace('{monitor}', (string) $monitor->id, $uri))
            ->assertSuccessful();
    }

    #[DataProvider('monitorRoutes')]
    public function test_missing_and_non_numeric_monitor_ids_are_not_found(string $method, string $uri): void
    {
        $this->actingAs(User::factory()->create())->fromSpa();

        $this->json($method, '/'.str_replace('{monitor}', '999999', $uri))->assertNotFound();
        $this->json($method, '/'.str_replace('{monitor}', 'abc', $uri))->assertNotFound();
    }

    public function test_every_monitor_route_is_covered_by_the_isolation_matrix(): void
    {
        $covered = collect(self::monitorRoutes())->map(fn (array $route) => implode(' ', $route))->sort()->values()->all();

        $existing = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_contains($route->uri(), '{monitor}'))
            ->map(fn (Route $route) => $route->methods()[0].' '.$route->uri())
            ->sort()
            ->values()
            ->all();

        $this->assertSame($covered, $existing);
    }

    public function test_monitor_list_only_contains_the_users_monitors(): void
    {
        $user = User::factory()->create();
        $ownMonitors = Monitor::factory()->for($user)->count(2)->create();
        Monitor::factory()->count(3)->create();

        $response = $this->actingAs($user)->fromSpa()->getJson('/api/monitors');

        $response->assertOk();
        $this->assertEqualsCanonicalizing($ownMonitors->pluck('id')->all(), $response->json('data.*.id'));
    }

    public function test_checks_and_incidents_lists_only_contain_the_monitors_own_records(): void
    {
        $user = User::factory()->create();
        $monitor = Monitor::factory()->for($user)->create();
        $ownCheck = CheckResult::factory()->for($monitor)->create();
        $ownIncident = Incident::factory()->for($monitor)->create();
        $otherMonitor = Monitor::factory()->for($user)->create();
        CheckResult::factory()->for($otherMonitor)->create();
        Incident::factory()->for($otherMonitor)->create();
        CheckResult::factory()->create();
        Incident::factory()->create();

        $this->actingAs($user)->fromSpa();

        $this->assertSame([$ownCheck->id], $this->getJson("/api/monitors/{$monitor->id}/checks")->assertOk()->json('data.*.id'));
        $this->assertSame([$ownIncident->id], $this->getJson("/api/monitors/{$monitor->id}/incidents")->assertOk()->json('data.*.id'));
    }

    public function test_monitor_resource_does_not_expose_internal_fields(): void
    {
        $monitor = Monitor::factory()->create();

        $response = $this->actingAs($monitor->user)->fromSpa()->getJson("/api/monitors/{$monitor->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $monitor->id)
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.consecutive_failures')
            ->assertJsonMissingPath('data.consecutive_successes');
    }
}
