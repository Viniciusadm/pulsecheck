<?php

namespace Tests\Feature\Checks;

use App\Jobs\RunMonitorCheck;
use App\Models\Monitor;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchDueMonitorsCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_only_due_active_monitors_and_advances_their_schedule(): void
    {
        Queue::fake();
        $this->freezeSecond();

        $due = Monitor::factory()->create(['interval_minutes' => 5, 'next_check_at' => now()->subMinute()]);
        $dueNow = Monitor::factory()->create(['interval_minutes' => 1, 'next_check_at' => now()]);
        $notDue = Monitor::factory()->create(['next_check_at' => now()->addMinute()]);
        $paused = Monitor::factory()->paused()->create(['next_check_at' => now()->subMinute()]);
        $unscheduled = Monitor::factory()->create(['next_check_at' => null]);

        $this->artisan('monitors:dispatch-due')->assertSuccessful();

        Queue::assertPushed(RunMonitorCheck::class, 2);
        Queue::assertPushed(RunMonitorCheck::class, fn (RunMonitorCheck $job) => $job->monitorId === $due->id);
        Queue::assertPushed(RunMonitorCheck::class, fn (RunMonitorCheck $job) => $job->monitorId === $dueNow->id);

        $this->assertTrue($due->fresh()->next_check_at->equalTo(now()->startOfMinute()->addMinutes(5)));
        $this->assertTrue($dueNow->fresh()->next_check_at->equalTo(now()->startOfMinute()->addMinute()));
        $this->assertTrue($notDue->fresh()->next_check_at->equalTo(now()->addMinute()));
        $this->assertNull($unscheduled->fresh()->next_check_at);
        $this->assertTrue($paused->fresh()->is_paused);
    }

    public function test_schedule_is_aligned_to_the_minute_so_a_late_run_does_not_skip_a_cycle(): void
    {
        Queue::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:01'));
        $monitor = Monitor::factory()->create(['interval_minutes' => 1, 'next_check_at' => now()->subSecond()]);

        $this->artisan('monitors:dispatch-due')->assertSuccessful();
        $this->assertTrue($monitor->fresh()->next_check_at->equalTo(CarbonImmutable::parse('2026-10-17 12:01:00')));

        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:01:00'));
        $this->artisan('monitors:dispatch-due')->assertSuccessful();

        $this->assertTrue($monitor->fresh()->next_check_at->equalTo(CarbonImmutable::parse('2026-10-17 12:02:00')));
    }

    public function test_an_immediate_second_run_does_not_dispatch_again(): void
    {
        Queue::fake();
        Monitor::factory()->count(3)->create(['next_check_at' => now()->subMinute()]);

        $this->artisan('monitors:dispatch-due')->assertSuccessful();
        $this->artisan('monitors:dispatch-due')->assertSuccessful();

        Queue::assertPushed(RunMonitorCheck::class, 3);
    }

    public function test_handles_more_monitors_than_one_chunk(): void
    {
        Queue::fake();
        Monitor::factory()->count(510)->create(['next_check_at' => now()->subMinute()]);

        $this->artisan('monitors:dispatch-due')->assertSuccessful();

        Queue::assertPushed(RunMonitorCheck::class, 510);
        $this->assertSame(0, Monitor::where('next_check_at', '<=', now())->count());
    }

    public function test_logs_a_check_skipped_because_the_previous_one_is_still_pending(): void
    {
        Queue::fake();
        Log::spy();
        $monitor = Monitor::factory()->create(['interval_minutes' => 1, 'next_check_at' => now()->subMinute()]);
        RunMonitorCheck::dispatch($monitor);
        $monitor->update(['next_check_at' => now()->subSecond()]);

        $this->artisan('monitors:dispatch-due')->assertSuccessful();

        Queue::assertPushed(RunMonitorCheck::class, 1);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $context === ['monitor_id' => $monitor->id]);
    }

    public function test_dispatcher_is_scheduled_every_minute_without_overlapping(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'monitors:dispatch-due'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }
}
