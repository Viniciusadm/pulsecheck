<?php

namespace Tests\Feature\Incidents;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecentIncidentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_the_users_incidents_newest_first_with_their_monitor(): void
    {
        $user = User::factory()->create();
        $monitor = Monitor::factory()->for($user)->create(['name' => 'API']);
        $older = Incident::factory()->for($monitor)->create(['started_at' => now()->subDays(2), 'resolved_at' => now()->subDays(2)->addMinutes(5)]);
        $newer = Incident::factory()->for($monitor)->open()->create(['started_at' => now()->subHour()]);
        Incident::factory()->count(2)->create();

        $response = $this->actingAs($user)->fromSpa()->getJson('/api/incidents');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.state', 'open')
            ->assertJsonPath('data.0.monitor.id', $monitor->id)
            ->assertJsonPath('data.0.monitor.name', 'API')
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonCount(2, 'data');
    }

    public function test_limit_defaults_to_ten_and_is_bounded(): void
    {
        $user = User::factory()->create();
        $monitor = Monitor::factory()->for($user)->create();
        Incident::factory()->for($monitor)->count(12)->create();

        $this->actingAs($user)->fromSpa();

        $this->getJson('/api/incidents')->assertOk()->assertJsonCount(10, 'data');
        $this->getJson('/api/incidents?limit=3')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/incidents?limit=0')->assertUnprocessable()->assertJsonValidationErrors('limit');
        $this->getJson('/api/incidents?limit=51')->assertUnprocessable()->assertJsonValidationErrors('limit');
    }

    public function test_monitor_incident_list_includes_derived_state_and_duration(): void
    {
        $this->freezeSecond();
        $monitor = Monitor::factory()->create();
        Incident::factory()->for($monitor)->open()->create(['started_at' => now()->subMinutes(2)]);

        $this->actingAs($monitor->user)->fromSpa()->getJson("/api/monitors/{$monitor->id}/incidents")
            ->assertOk()
            ->assertJsonPath('data.0.state', 'open')
            ->assertJsonPath('data.0.duration_seconds', 120)
            ->assertJsonPath('data.0.resolved_at', null);
    }
}
