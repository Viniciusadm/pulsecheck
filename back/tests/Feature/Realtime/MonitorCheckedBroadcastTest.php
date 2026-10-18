<?php

namespace Tests\Feature\Realtime;

use App\Actions\RecordCheckResult;
use App\Enums\CheckErrorType;
use App\Events\MonitorChecked;
use App\Models\Monitor;
use App\Services\CheckOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class MonitorCheckedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function record(Monitor $monitor, bool $isSuccess = true): void
    {
        $now = CarbonImmutable::now();

        app(RecordCheckResult::class)->handle($monitor, new CheckOutcome(
            $now, $now, $isSuccess ? 120 : null, $isSuccess ? 200 : null, $isSuccess,
            $isSuccess ? null : CheckErrorType::Timeout, $isSuccess ? null : 'Operation timed out',
        ));
    }

    public function test_recording_a_check_broadcasts_the_updated_monitor_to_every_user(): void
    {
        Event::fake([MonitorChecked::class]);
        $monitor = Monitor::factory()->create();

        $this->record($monitor);

        Event::assertDispatched(MonitorChecked::class, function (MonitorChecked $event) use ($monitor): bool {
            $payload = $event->broadcastWith();

            return $event->broadcastOn()->name === 'private-monitors'
                && $event->broadcastAs() === 'monitor.checked'
                && $payload['monitor']['id'] === $monitor->id
                && $payload['monitor']['last_status_code'] === 200
                && $payload['monitor']['last_latency_ms'] === 120;
        });
    }

    public function test_discarded_checks_are_not_broadcast(): void
    {
        Event::fake([MonitorChecked::class]);
        $monitor = Monitor::factory()->create(['url' => 'https://example.com']);
        Monitor::query()->whereKey($monitor->id)->update(['url' => 'https://example.org']);

        $this->record($monitor);

        Event::assertNotDispatched(MonitorChecked::class);
    }

    public function test_a_broadcast_failure_does_not_lose_the_recorded_check(): void
    {
        Broadcast::shouldReceive('queue')->andThrow(new RuntimeException('Reverb is down'));
        $monitor = Monitor::factory()->create();

        $this->record($monitor, false);

        $this->assertSame(1, $monitor->checkResults()->count());
        $this->assertNotNull($monitor->refresh()->last_failed_at);
    }
}
