<?php

namespace Tests\Feature\Notifications;

use App\Actions\RecordCheckResult;
use App\Enums\CheckErrorType;
use App\Enums\NotificationType;
use App\Events\MonitorRecovered;
use App\Events\MonitorWentDown;
use App\Jobs\SendIncidentNotification;
use App\Listeners\QueueIncidentNotifications;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Services\CheckOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function record(Monitor $monitor, bool $isSuccess): void
    {
        $now = CarbonImmutable::now();

        app(RecordCheckResult::class)->handle($monitor, new CheckOutcome(
            $now, $now, $isSuccess ? 100 : null, $isSuccess ? 200 : null, $isSuccess,
            $isSuccess ? null : CheckErrorType::Timeout, $isSuccess ? null : 'Operation timed out',
        ));
        $this->travel(1)->minute();
    }

    private function monitorWithChannels(NotificationChannel ...$channels): Monitor
    {
        $monitor = Monitor::factory()->up()->create();
        $monitor->notificationChannels()->attach(collect($channels)->pluck('id'));

        return $monitor;
    }

    public function test_listener_is_registered_for_both_transitions(): void
    {
        Event::fake();

        Event::assertListening(MonitorWentDown::class, QueueIncidentNotifications::class);
        Event::assertListening(MonitorRecovered::class, QueueIncidentNotifications::class);
    }

    public function test_going_down_queues_one_notification_per_enabled_attached_channel(): void
    {
        Queue::fake();
        $telegram = NotificationChannel::factory()->telegram()->create();
        $slack = NotificationChannel::factory()->slack()->create();
        $disabled = NotificationChannel::factory()->disabled()->create();
        $unattached = NotificationChannel::factory()->email()->create();
        $monitor = $this->monitorWithChannels($telegram, $slack, $disabled);

        $this->record($monitor, false);
        Queue::assertNothingPushed();

        $this->record($monitor, false);
        $this->record($monitor, false);
        $this->record($monitor, false);

        $incident = $monitor->incidents()->sole();
        Queue::assertPushed(SendIncidentNotification::class, 2);

        foreach ([$telegram, $slack] as $channel) {
            Queue::assertPushed(SendIncidentNotification::class, fn (SendIncidentNotification $job) => $job->incidentId === $incident->id
                && $job->channelId === $channel->id
                && $job->type === NotificationType::Down);
        }

        Queue::assertNotPushed(SendIncidentNotification::class, fn (SendIncidentNotification $job) => in_array($job->channelId, [$disabled->id, $unattached->id], true));
    }

    public function test_recovery_queues_notifications(): void
    {
        Queue::fake();
        $monitor = $this->monitorWithChannels(NotificationChannel::factory()->create());

        foreach ([false, false, true, true, true] as $isSuccess) {
            $this->record($monitor, $isSuccess);
        }

        Queue::assertPushed(SendIncidentNotification::class, 2);
        Queue::assertPushed(SendIncidentNotification::class, fn (SendIncidentNotification $job) => $job->type === NotificationType::Recovered);
    }

    public function test_pending_monitor_becoming_up_does_not_notify(): void
    {
        Queue::fake();
        $channel = NotificationChannel::factory()->create();
        $monitor = Monitor::factory()->create();
        $monitor->notificationChannels()->attach($channel);

        $this->record($monitor, true);
        $this->record($monitor, true);

        Queue::assertNothingPushed();
    }

    public function test_monitors_without_channels_get_no_notifications_and_checks_continue(): void
    {
        Queue::fake();
        $monitor = Monitor::factory()->up()->create();
        NotificationChannel::factory()->create();

        $this->record($monitor, false);
        $this->record($monitor, false);

        Queue::assertNothingPushed();
        $this->assertSame(1, $monitor->incidents()->count());
    }

    public function test_notification_job_is_unique_per_incident_channel_and_type(): void
    {
        Queue::fake();

        SendIncidentNotification::dispatch(10, 1, NotificationType::Down);
        SendIncidentNotification::dispatch(10, 1, NotificationType::Down);
        SendIncidentNotification::dispatch(10, 2, NotificationType::Down);
        SendIncidentNotification::dispatch(10, 1, NotificationType::Recovered);
        SendIncidentNotification::dispatch(11, 1, NotificationType::Down);

        Queue::assertPushed(SendIncidentNotification::class, 4);
    }
}
