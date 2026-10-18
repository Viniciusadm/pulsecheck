<?php

namespace Tests\Feature\Models;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rejects_a_second_open_incident_for_the_same_monitor(): void
    {
        $monitor = Monitor::factory()->create();
        Incident::factory()->for($monitor)->open()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23505');

        Incident::factory()->for($monitor)->open()->create();
    }

    public function test_monitor_can_have_many_resolved_incidents_and_one_open(): void
    {
        $monitor = Monitor::factory()->create();

        Incident::factory()->for($monitor)->resolved()->count(2)->create();
        Incident::factory()->for($monitor)->open()->create();

        $this->assertSame(3, $monitor->incidents()->count());
    }

    public function test_different_monitors_can_each_have_an_open_incident(): void
    {
        Incident::factory()->open()->create();
        Incident::factory()->open()->create();

        $this->assertSame(2, Incident::query()->whereNull('resolved_at')->count());
    }

    public function test_open_incident_state_and_duration_are_derived(): void
    {
        $this->freezeTime();

        $incident = Incident::factory()->open()->create(['started_at' => now()->subMinutes(3)]);

        $this->assertTrue($incident->is_open);
        $this->assertSame('open', $incident->state);
        $this->assertSame(180, $incident->duration_seconds);
    }

    public function test_resolved_incident_duration_ends_at_resolution(): void
    {
        $incident = Incident::factory()->create([
            'started_at' => now()->subHour(),
            'resolved_at' => now()->subHour()->addSeconds(90),
        ]);

        $this->assertFalse($incident->is_open);
        $this->assertSame('resolved', $incident->state);
        $this->assertSame(90, $incident->duration_seconds);
    }
}
