<?php

namespace Tests\Feature\Incidents;

use App\Actions\RecordCheckResult;
use App\Enums\CheckErrorType;
use App\Enums\IncidentKind;
use App\Enums\MonitorStatus;
use App\Enums\NotificationType;
use App\Enums\StatsPeriod;
use App\Enums\WarningType;
use App\Events\MonitorDegradationResolved;
use App\Events\MonitorDegraded;
use App\Events\MonitorWentDown;
use App\Jobs\SendIncidentNotification;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use App\Services\CheckOutcome;
use App\Services\DashboardMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DegradationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = CarbonImmutable::parse('2026-10-17 12:00:00');
        $this->travelTo($this->clock);
    }

    private function record(Monitor $monitor, string $result): void
    {
        $startedAt = $this->clock;
        $this->clock = $this->clock->addMinute();
        $this->travelTo($this->clock);

        app(RecordCheckResult::class)->handle($monitor, match ($result) {
            'ok' => new CheckOutcome($startedAt, $startedAt, 150, 200, true, null, null),
            'slow' => new CheckOutcome($startedAt, $startedAt, 2500, 200, true, null, null, true, WarningType::SlowResponse, 'Response time 2500 ms exceeds 1000 ms.'),
            'fail' => new CheckOutcome($startedAt, $startedAt, null, null, false, CheckErrorType::Timeout, 'Operation timed out'),
        });
    }

    public function test_slow_checks_open_and_healthy_checks_resolve_a_degradation_incident(): void
    {
        Event::fake([MonitorDegraded::class, MonitorDegradationResolved::class]);
        $monitor = Monitor::factory()->up()->create();

        $this->record($monitor, 'slow');
        $this->record($monitor, 'slow');

        $incident = $monitor->incidents()->sole();
        $this->assertSame(IncidentKind::Degradation, $incident->kind);
        $this->assertSame('slow_response: Response time 2500 ms exceeds 1000 ms.', $incident->cause);
        $this->assertTrue($incident->started_at->equalTo(CarbonImmutable::parse('2026-10-17 12:00:00')));
        $this->assertSame(MonitorStatus::Degraded, $monitor->fresh()->status);
        $this->assertTrue($monitor->checkResults()->latest('id')->first()->is_degraded);
        Event::assertDispatchedTimes(MonitorDegraded::class, 1);

        $this->record($monitor, 'ok');
        $this->record($monitor, 'ok');

        $this->assertFalse($incident->fresh()->is_open);
        $this->assertSame(MonitorStatus::Up, $monitor->fresh()->status);
        Event::assertDispatchedTimes(MonitorDegradationResolved::class, 1);
    }

    public function test_going_down_while_degraded_closes_the_degradation_and_opens_an_outage(): void
    {
        Event::fake([MonitorDegraded::class, MonitorDegradationResolved::class, MonitorWentDown::class]);
        $monitor = Monitor::factory()->up()->create();

        $this->record($monitor, 'slow');
        $this->record($monitor, 'slow');
        $this->record($monitor, 'fail');
        $this->record($monitor, 'fail');

        $degradation = $monitor->incidents()->where('kind', IncidentKind::Degradation)->sole();
        $outage = $monitor->incidents()->where('kind', IncidentKind::Outage)->sole();
        $this->assertFalse($degradation->is_open);
        $this->assertTrue($outage->is_open);
        $this->assertSame(MonitorStatus::Down, $monitor->fresh()->status);
        Event::assertNotDispatched(MonitorDegradationResolved::class);
        Event::assertDispatchedTimes(MonitorWentDown::class, 1);
    }

    public function test_degradation_is_notified_on_enabled_channels(): void
    {
        Queue::fake();
        $channel = NotificationChannel::factory()->create();
        $monitor = Monitor::factory()->up()->create();
        $monitor->notificationChannels()->attach($channel);

        $this->record($monitor, 'slow');
        $this->record($monitor, 'slow');

        Queue::assertPushed(SendIncidentNotification::class, fn (SendIncidentNotification $job) => $job->type === NotificationType::Degraded && $job->channelId === $channel->id);
    }

    public function test_degradation_does_not_count_in_the_incident_timeline_or_uptime(): void
    {
        $user = User::factory()->admin()->create();
        $monitor = Monitor::factory()->up()->create();

        $this->record($monitor, 'slow');
        $this->record($monitor, 'slow');

        $timeline = app(DashboardMetrics::class)->incidentTimeline($user, StatsPeriod::Day);
        $this->assertSame(0, array_sum(array_column($timeline, 'incidents')));

        $this->actingAs($user)->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}/stats?period=24h")
            ->assertOk()
            ->assertJsonPath('data.uptime_percentage', 100)
            ->assertJsonPath('data.degraded_checks', 2);
    }
}
