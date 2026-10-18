<?php

namespace Tests\Feature\Realtime;

use App\Actions\RecordCheckResult;
use App\Enums\CheckErrorType;
use App\Events\MonitorChecked;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use App\Services\CheckOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\PrivateChannel;
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

    public function test_recording_a_check_broadcasts_the_updated_monitor_to_every_user_who_can_see_it(): void
    {
        Event::fake([MonitorChecked::class]);
        $team = Team::factory()->create();
        $monitor = Monitor::factory()->create();
        $monitor->teams()->attach($team);
        $admin = User::factory()->admin()->create();
        $viewingAll = User::factory()->viewingAll()->create();
        $teamMember = User::factory()->create();
        $teamMember->teams()->attach($team);
        User::factory()->create()->teams()->attach(Team::factory()->create());
        User::factory()->create();

        $this->record($monitor);

        $expectedChannels = collect([$admin, $viewingAll, $teamMember])
            ->map(fn (User $user) => "private-users.{$user->id}.monitors")
            ->sort()
            ->values()
            ->all();

        Event::assertDispatched(MonitorChecked::class, function (MonitorChecked $event) use ($monitor, $expectedChannels): bool {
            $payload = $event->broadcastWith();
            $channels = collect($event->broadcastOn())->map(fn (PrivateChannel $channel) => $channel->name)->sort()->values()->all();

            return $channels === $expectedChannels
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
