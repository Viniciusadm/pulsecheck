<?php

namespace Tests\Feature\Monitors;

use App\Actions\UpdateMonitor;
use App\Enums\MonitorStatus;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesDns;
use Tests\TestCase;

class UpdateMonitorTest extends TestCase
{
    use FakesDns;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns([
            'example.com' => ['93.184.215.14'],
            'new.example.com' => ['93.184.215.15'],
        ]);
    }

    public function test_renaming_keeps_status_counters_and_schedule(): void
    {
        $nextCheckAt = now()->addMinutes(4)->startOfSecond();
        $monitor = Monitor::factory()->down()->create(['url' => 'https://example.com', 'next_check_at' => $nextCheckAt]);
        $openIncident = Incident::factory()->for($monitor)->open()->create();

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->patchJson("/api/monitors/{$monitor->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $monitor->refresh();
        $this->assertSame(MonitorStatus::Down, $monitor->status);
        $this->assertSame(3, $monitor->consecutive_failures);
        $this->assertTrue($monitor->next_check_at->equalTo($nextCheckAt));
        $this->assertNull($openIncident->fresh()->resolved_at);
    }

    public function test_changing_the_url_resets_state_and_resolves_the_open_incident(): void
    {
        $this->freezeSecond();
        $monitor = Monitor::factory()->down()->create(['url' => 'https://example.com', 'next_check_at' => now()->addMinutes(4)]);
        $openIncident = Incident::factory()->for($monitor)->open()->create();

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->putJson("/api/monitors/{$monitor->id}", ['url' => 'https://new.example.com/'])
            ->assertOk()
            ->assertJsonPath('data.url', 'https://new.example.com/')
            ->assertJsonPath('data.status', MonitorStatus::Pending->value);

        $monitor->refresh();
        $this->assertSame(0, $monitor->consecutive_failures);
        $this->assertSame(0, $monitor->consecutive_successes);
        $this->assertNull($monitor->last_status_code);
        $this->assertTrue($monitor->next_check_at->equalTo(now()));
        $this->assertTrue($openIncident->fresh()->resolved_at->equalTo(now()));
    }

    public function test_changing_the_url_uses_the_current_row_not_a_stale_copy(): void
    {
        $monitor = Monitor::factory()->up()->create(['url' => 'https://example.com']);
        $staleCopy = Monitor::findOrFail($monitor->id);
        Monitor::whereKey($monitor->id)->update(['status' => 'down', 'consecutive_failures' => 2]);
        $openIncident = Incident::factory()->for($monitor)->open()->create();

        app(UpdateMonitor::class)->handle(User::factory()->admin()->create(), $staleCopy, ['url' => 'https://new.example.com/']);

        $monitor->refresh();
        $this->assertSame(MonitorStatus::Pending, $monitor->status);
        $this->assertSame(0, $monitor->consecutive_failures);
        $this->assertNotNull($openIncident->fresh()->resolved_at);
    }

    public function test_changing_the_url_of_a_paused_monitor_keeps_it_unscheduled(): void
    {
        $monitor = Monitor::factory()->paused()->create(['url' => 'https://example.com', 'next_check_at' => null]);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->patchJson("/api/monitors/{$monitor->id}", ['url' => 'https://new.example.com/'])
            ->assertOk();

        $monitor->refresh();
        $this->assertTrue($monitor->is_paused);
        $this->assertNull($monitor->next_check_at);
    }

    public function test_changing_the_interval_reschedules_the_next_check(): void
    {
        $this->freezeSecond();
        $monitor = Monitor::factory()->create(['interval_minutes' => 10, 'next_check_at' => now()->addMinutes(9)]);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->patchJson("/api/monitors/{$monitor->id}", ['interval_minutes' => 1])
            ->assertOk()
            ->assertJsonPath('data.interval_minutes', 1);

        $this->assertTrue($monitor->fresh()->next_check_at->equalTo(now()));
    }

    public function test_update_validates_fields_that_are_sent(): void
    {
        $monitor = Monitor::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $this->patchJson("/api/monitors/{$monitor->id}", ['url' => 'http://127.0.0.1'])->assertJsonValidationErrors('url');
        $this->patchJson("/api/monitors/{$monitor->id}", ['interval_minutes' => 2])->assertJsonValidationErrors('interval_minutes');
        $this->patchJson("/api/monitors/{$monitor->id}", ['timeout_seconds' => 31])->assertJsonValidationErrors('timeout_seconds');
        $this->patchJson("/api/monitors/{$monitor->id}", ['name' => ''])->assertJsonValidationErrors('name');
    }

    public function test_timeout_is_compared_with_the_effective_interval(): void
    {
        config(['pulsecheck.timeout_max' => 120]);
        $monitor = Monitor::factory()->create(['interval_minutes' => 1, 'timeout_seconds' => 10]);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->patchJson("/api/monitors/{$monitor->id}", ['timeout_seconds' => 60])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('timeout_seconds');
    }
}
