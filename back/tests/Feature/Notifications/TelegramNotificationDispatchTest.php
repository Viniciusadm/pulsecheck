<?php

namespace Tests\Feature\Notifications;

use App\Actions\RecordCheckResult;
use App\Enums\CheckErrorType;
use App\Enums\NotificationType;
use App\Events\MonitorRecovered;
use App\Events\MonitorWentDown;
use App\Jobs\SendTelegramNotification;
use App\Listeners\QueueTelegramNotification;
use App\Models\Monitor;
use App\Models\NotificationSetting;
use App\Services\CheckOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramNotificationDispatchTest extends TestCase
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

    private function monitorWithTelegram(bool $enabled = true): Monitor
    {
        $setting = NotificationSetting::factory()->withTelegram()->create(['telegram_enabled' => $enabled]);

        return Monitor::factory()->for($setting->user)->up()->create();
    }

    public function test_listener_is_registered_for_both_transitions(): void
    {
        Event::fake();

        Event::assertListening(MonitorWentDown::class, QueueTelegramNotification::class);
        Event::assertListening(MonitorRecovered::class, QueueTelegramNotification::class);
    }

    public function test_going_down_queues_exactly_one_notification(): void
    {
        Queue::fake();
        $monitor = $this->monitorWithTelegram();

        $this->record($monitor, false);
        Queue::assertNothingPushed();

        $this->record($monitor, false);
        $this->record($monitor, false);
        $this->record($monitor, false);

        $incident = $monitor->incidents()->sole();
        Queue::assertPushed(SendTelegramNotification::class, 1);
        Queue::assertPushed(SendTelegramNotification::class, fn (SendTelegramNotification $job) => $job->incidentId === $incident->id && $job->type === NotificationType::Down);
    }

    public function test_recovery_queues_exactly_one_notification(): void
    {
        Queue::fake();
        $monitor = $this->monitorWithTelegram();

        foreach ([false, false, true, true, true] as $isSuccess) {
            $this->record($monitor, $isSuccess);
        }

        Queue::assertPushed(SendTelegramNotification::class, 2);
        Queue::assertPushed(SendTelegramNotification::class, fn (SendTelegramNotification $job) => $job->type === NotificationType::Recovered);
    }

    public function test_pending_monitor_becoming_up_does_not_notify(): void
    {
        Queue::fake();
        $setting = NotificationSetting::factory()->withTelegram()->create();
        $monitor = Monitor::factory()->for($setting->user)->create();

        $this->record($monitor, true);
        $this->record($monitor, true);

        Queue::assertNothingPushed();
    }

    public function test_users_without_enabled_telegram_get_no_notifications_and_checks_continue(): void
    {
        Queue::fake();
        $disabled = $this->monitorWithTelegram(enabled: false);
        $unconfigured = Monitor::factory()->up()->create();

        foreach ([$disabled, $unconfigured] as $monitor) {
            $this->record($monitor, false);
            $this->record($monitor, false);
        }

        Queue::assertNothingPushed();
        $this->assertSame(1, $disabled->incidents()->count());
        $this->assertSame(1, $unconfigured->incidents()->count());
    }

    public function test_notification_job_is_unique_per_incident_and_type(): void
    {
        Queue::fake();

        SendTelegramNotification::dispatch(10, NotificationType::Down);
        SendTelegramNotification::dispatch(10, NotificationType::Down);
        SendTelegramNotification::dispatch(10, NotificationType::Recovered);
        SendTelegramNotification::dispatch(11, NotificationType::Down);

        Queue::assertPushed(SendTelegramNotification::class, 3);
    }
}
