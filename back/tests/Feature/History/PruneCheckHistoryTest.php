<?php

namespace Tests\Feature\History;

use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCheckResults;
use Tests\TestCase;

class PruneCheckHistoryTest extends TestCase
{
    use CreatesCheckResults;
    use RefreshDatabase;

    public function test_prune_removes_only_results_older_than_the_retention(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00'));
        config(['pulsecheck.history_retention_days' => 30]);
        $monitor = Monitor::factory()->create();
        $old = $this->checkAt($monitor, now()->subDays(30)->subSecond());
        $boundary = $this->checkAt($monitor, now()->subDays(30));
        $recent = $this->checkAt($monitor, now()->subDay());
        $oldIncident = Incident::factory()->for($monitor)->create(['started_at' => now()->subYear(), 'resolved_at' => now()->subYear()->addHour()]);

        $this->artisan('model:prune', ['--model' => [CheckResult::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($boundary);
        $this->assertModelExists($recent);
        $this->assertModelExists($oldIncident);
    }

    public function test_prune_is_scheduled_daily(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'model:prune'));

        $this->assertNotNull($event);
        $this->assertSame('0 3 * * *', $event->expression);
        $this->assertTrue($event->onOneServer);
    }
}
