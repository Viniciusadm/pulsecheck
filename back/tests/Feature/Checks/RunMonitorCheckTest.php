<?php

namespace Tests\Feature\Checks;

use App\Enums\CheckErrorType;
use App\Enums\MonitorStatus;
use App\Jobs\RunMonitorCheck;
use App\Models\Monitor;
use App\Security\SafeHttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\FakesDns;
use Tests\TestCase;

class RunMonitorCheckTest extends TestCase
{
    use FakesDns;
    use RefreshDatabase;

    private MockHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns(['example.com' => ['93.184.215.14']]);
        $this->handler = new MockHandler;
        $this->app->instance(SafeHttpClient::class, $this->app->make(SafeHttpClient::class, ['handler' => $this->handler]));
    }

    private function runCheck(Monitor $monitor): void
    {
        Bus::dispatchSync(new RunMonitorCheck($monitor));
    }

    public function test_successful_check_is_recorded_and_the_monitor_updated(): void
    {
        $this->freezeSecond();
        $scheduledAt = now()->addMinutes(5)->startOfMinute();
        $monitor = Monitor::factory()->up()->create(['url' => 'https://example.com', 'interval_minutes' => 5, 'consecutive_successes' => 0, 'consecutive_failures' => 1, 'next_check_at' => $scheduledAt]);
        $this->handler->append(new Response(204));

        $this->runCheck($monitor);

        $result = $monitor->checkResults()->sole();
        $this->assertTrue($result->is_success);
        $this->assertSame(204, $result->status_code);
        $this->assertNotNull($result->latency_ms);
        $this->assertNotNull($result->started_at);
        $this->assertNotNull($result->finished_at);
        $this->assertNull($result->error_type);

        $monitor->refresh();
        $this->assertSame(204, $monitor->last_status_code);
        $this->assertSame($result->latency_ms, $monitor->last_latency_ms);
        $this->assertTrue($monitor->last_checked_at->equalTo($result->finished_at));
        $this->assertSame(1, $monitor->consecutive_successes);
        $this->assertSame(0, $monitor->consecutive_failures);
        $this->assertNull($monitor->last_failed_at);
        $this->assertTrue($monitor->next_check_at->equalTo($scheduledAt));
    }

    public function test_failed_check_is_recorded_with_its_error(): void
    {
        $monitor = Monitor::factory()->create(['url' => 'https://example.com', 'consecutive_successes' => 4]);
        $this->handler->append(new Response(503));

        $this->runCheck($monitor);

        $result = $monitor->checkResults()->sole();
        $this->assertFalse($result->is_success);
        $this->assertSame(503, $result->status_code);
        $this->assertSame(CheckErrorType::HttpStatus, $result->error_type);
        $this->assertSame('HTTP 503', $result->error_message);

        $monitor->refresh();
        $this->assertSame(1, $monitor->consecutive_failures);
        $this->assertSame(0, $monitor->consecutive_successes);
        $this->assertTrue($monitor->last_failed_at->equalTo($result->finished_at));
    }

    public function test_counters_are_capped_to_fit_the_column(): void
    {
        $monitor = Monitor::factory()->up()->create(['url' => 'https://example.com', 'consecutive_successes' => 32767]);
        $this->handler->append(new Response(200));

        $this->runCheck($monitor);

        $this->assertSame(32767, $monitor->fresh()->consecutive_successes);
    }

    public function test_a_single_success_keeps_a_pending_monitor_pending(): void
    {
        $monitor = Monitor::factory()->create(['url' => 'https://example.com']);
        $this->handler->append(new Response(200));

        $this->runCheck($monitor);

        $this->assertSame(MonitorStatus::Pending, $monitor->fresh()->status);
    }

    public function test_a_failure_on_an_up_monitor_is_confirmed_after_half_the_interval(): void
    {
        $this->freezeSecond();
        $monitor = Monitor::factory()->up()->create(['url' => 'https://example.com', 'interval_minutes' => 10, 'next_check_at' => now()->addMinutes(10)->startOfMinute()]);
        $this->handler->append(new Response(503));

        $this->runCheck($monitor);

        $monitor->refresh();
        $this->assertSame(MonitorStatus::Up, $monitor->status);
        $this->assertTrue($monitor->next_check_at->equalTo(now()->startOfMinute()->addMinutes(5)));
    }

    public function test_a_success_on_a_down_monitor_is_confirmed_after_half_the_interval(): void
    {
        $this->freezeSecond();
        $monitor = Monitor::factory()->down()->create(['url' => 'https://example.com', 'interval_minutes' => 10, 'next_check_at' => now()->addMinutes(10)->startOfMinute()]);
        $this->handler->append(new Response(200));

        $this->runCheck($monitor);

        $monitor->refresh();
        $this->assertSame(MonitorStatus::Down, $monitor->status);
        $this->assertTrue($monitor->next_check_at->equalTo(now()->startOfMinute()->addMinutes(5)));
    }

    public function test_a_pending_monitor_is_checked_again_after_half_the_interval(): void
    {
        $this->freezeSecond();
        $monitor = Monitor::factory()->create(['url' => 'https://example.com', 'interval_minutes' => 5, 'next_check_at' => now()->addMinutes(5)->startOfMinute()]);
        $this->handler->append(new Response(200));

        $this->runCheck($monitor);

        $this->assertTrue($monitor->fresh()->next_check_at->equalTo(now()->startOfMinute()->addSeconds(150)));
    }

    public function test_the_confirmation_check_waits_at_least_one_minute(): void
    {
        $this->freezeSecond();
        $monitor = Monitor::factory()->up()->create(['url' => 'https://example.com', 'interval_minutes' => 1, 'next_check_at' => now()->addMinute()->startOfMinute()]);
        $this->handler->append(new Response(503));

        $this->runCheck($monitor);

        $this->assertTrue($monitor->fresh()->next_check_at->equalTo(now()->startOfMinute()->addMinute()));
    }

    public function test_a_result_that_matches_the_status_keeps_the_regular_interval(): void
    {
        $this->freezeSecond();
        $scheduledAt = now()->addMinutes(10)->startOfMinute();
        $monitor = Monitor::factory()->up()->create(['url' => 'https://example.com', 'interval_minutes' => 10, 'next_check_at' => $scheduledAt]);
        $this->handler->append(new Response(200));

        $this->runCheck($monitor);

        $this->assertTrue($monitor->fresh()->next_check_at->equalTo($scheduledAt));
    }

    public function test_the_confirmation_check_never_postpones_an_earlier_one(): void
    {
        $this->freezeSecond();
        $scheduledAt = now()->addMinute()->startOfMinute();
        $monitor = Monitor::factory()->up()->create(['url' => 'https://example.com', 'interval_minutes' => 10, 'next_check_at' => $scheduledAt]);
        $this->handler->append(new Response(503));

        $this->runCheck($monitor);

        $this->assertTrue($monitor->fresh()->next_check_at->equalTo($scheduledAt));
    }

    public function test_paused_monitor_is_not_checked(): void
    {
        $monitor = Monitor::factory()->paused()->create(['url' => 'https://example.com']);
        $this->handler->append(new Response(200));

        $this->runCheck($monitor);

        $this->assertSame(0, $monitor->checkResults()->count());
        $this->assertCount(1, $this->handler);
    }

    public function test_deleted_monitor_is_ignored(): void
    {
        $monitor = Monitor::factory()->create(['url' => 'https://example.com']);
        $job = new RunMonitorCheck($monitor);
        $monitor->delete();
        $this->handler->append(new Response(200));

        Bus::dispatchSync($job);

        $this->assertDatabaseCount('check_results', 0);
        $this->assertCount(1, $this->handler);
    }

    public function test_monitor_paused_during_the_check_is_recorded_but_not_rescheduled(): void
    {
        $monitor = Monitor::factory()->create(['url' => 'https://example.com']);
        $this->handler->append(function () use ($monitor) {
            Monitor::whereKey($monitor->id)->update(['is_paused' => true, 'next_check_at' => null]);

            return new Response(200);
        });

        $this->runCheck($monitor);

        $this->assertSame(1, $monitor->checkResults()->count());
        $this->assertNull($monitor->fresh()->next_check_at);
    }

    public function test_result_of_a_check_against_a_url_changed_during_the_check_is_discarded(): void
    {
        $monitor = Monitor::factory()->create(['url' => 'https://example.com', 'consecutive_failures' => 1]);
        $this->handler->append(function () use ($monitor) {
            Monitor::whereKey($monitor->id)->update(['url' => 'https://example.org', 'consecutive_failures' => 0]);

            return new Response(503);
        });

        $this->runCheck($monitor);

        $this->assertSame(0, $monitor->checkResults()->count());
        $this->assertSame(0, $monitor->fresh()->consecutive_failures);
        $this->assertSame(0, $monitor->incidents()->count());
    }

    public function test_monitor_paused_during_a_failing_check_does_not_go_down(): void
    {
        $monitor = Monitor::factory()->up()->create(['url' => 'https://example.com', 'consecutive_successes' => 0, 'consecutive_failures' => 1]);
        $this->handler->append(function () use ($monitor) {
            Monitor::whereKey($monitor->id)->update(['is_paused' => true, 'next_check_at' => null]);

            return new Response(503);
        });

        $this->runCheck($monitor);

        $this->assertSame(1, $monitor->checkResults()->count());
        $this->assertSame(MonitorStatus::Up, $monitor->fresh()->status);
        $this->assertSame(0, $monitor->incidents()->count());
    }

    public function test_job_is_unique_per_monitor_and_not_retried(): void
    {
        Queue::fake();
        $monitor = Monitor::factory()->create(['interval_minutes' => 5, 'timeout_seconds' => 10]);

        RunMonitorCheck::dispatch($monitor);
        RunMonitorCheck::dispatch($monitor);
        RunMonitorCheck::dispatch(Monitor::factory()->create());

        Queue::assertPushed(RunMonitorCheck::class, 2);
        $this->assertCount(1, Queue::pushed(RunMonitorCheck::class, fn (RunMonitorCheck $job) => $job->monitorId === $monitor->id));

        $job = new RunMonitorCheck($monitor);
        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame((string) $monitor->id, (string) $job->uniqueId());
        $this->assertSame(300, $job->uniqueFor);
        $this->assertSame(1, $job->tries);
        $this->assertGreaterThan(10, $job->timeout);
    }
}
