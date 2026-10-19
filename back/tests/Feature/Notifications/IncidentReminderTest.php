<?php

namespace Tests\Feature\Notifications;

use App\Enums\IncidentKind;
use App\Enums\NotificationType;
use App\Jobs\SendIncidentNotification;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesDns;
use Tests\Concerns\FakesOutboundHttp;
use Tests\TestCase;

class IncidentReminderTest extends TestCase
{
    use FakesDns;
    use FakesOutboundHttp;
    use RefreshDatabase;

    private Monitor $monitor;

    private NotificationChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00'));
        $this->fakeDns(['hooks.example.com' => ['93.184.215.14']]);
        $this->channel = NotificationChannel::factory()->create(['config' => ['url' => 'https://hooks.example.com/pulse', 'headers' => []]]);
        $this->monitor = Monitor::factory()->down()->create(['name' => 'Shop', 'url' => 'https://shop.example.com', 'reminder_interval_minutes' => 15]);
        $this->monitor->notificationChannels()->attach($this->channel);
    }

    private function openIncident(array $attributes = []): Incident
    {
        return Incident::factory()->for($this->monitor)->open()->create([
            'started_at' => now()->subMinutes(20),
            'created_at' => now()->subMinutes(16),
            ...$attributes,
        ]);
    }

    public function test_reminder_is_queued_once_per_interval(): void
    {
        Queue::fake();
        $incident = $this->openIncident();

        $this->artisan('incidents:send-reminders')->assertSuccessful();
        $this->artisan('incidents:send-reminders')->assertSuccessful();

        Queue::assertPushed(SendIncidentNotification::class, 1);
        Queue::assertPushed(SendIncidentNotification::class, fn (SendIncidentNotification $job) => $job->type === NotificationType::Reminder
            && $job->incidentId === $incident->id
            && $job->sequence === 1
            && $job->uniqueId() === "{$incident->id}:{$this->channel->id}:reminder:1");

        $incident->refresh();
        $this->assertSame(1, $incident->reminder_count);
        $this->assertTrue($incident->last_reminded_at->equalTo(now()));

        $this->travel(15)->minutes();
        $this->artisan('incidents:send-reminders')->assertSuccessful();
        Queue::assertPushed(SendIncidentNotification::class, 2);
        $this->assertSame(2, $incident->fresh()->reminder_count);
    }

    public function test_nothing_is_sent_before_the_interval(): void
    {
        Queue::fake();
        $this->openIncident(['created_at' => now()->subMinutes(14)]);

        $this->artisan('incidents:send-reminders')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public static function skippedCases(): array
    {
        return [
            'acknowledged' => [['acknowledged_at' => '2026-10-17 11:50:00'], []],
            'resolved' => [['resolved_at' => '2026-10-17 11:55:00'], []],
            'degradation' => [['kind' => IncidentKind::Degradation], []],
            'paused monitor' => [[], ['is_paused' => true]],
            'reminders off' => [[], ['reminder_interval_minutes' => null]],
        ];
    }

    #[DataProvider('skippedCases')]
    public function test_reminders_are_skipped(array $incidentAttributes, array $monitorAttributes): void
    {
        Queue::fake();
        $this->monitor->forceFill($monitorAttributes)->save();
        $this->openIncident($incidentAttributes);

        $this->artisan('incidents:send-reminders')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_disabled_channels_get_no_reminder(): void
    {
        Queue::fake();
        $this->channel->update(['enabled' => false]);
        $this->openIncident();

        $this->artisan('incidents:send-reminders')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_webhook_receives_the_still_down_event(): void
    {
        $this->fakeOutboundHttp(new Response(204));
        $incident = $this->openIncident(['reminder_count' => 2]);

        Bus::dispatchSync(new SendIncidentNotification($incident->id, $this->channel->id, NotificationType::Reminder, 2));

        $payload = $this->outboundRequests[0]['json'];
        $this->assertSame('monitor.still_down', $payload['event']);
        $this->assertSame('critical', $payload['severity']);
        $this->assertSame('🔴 Shop is still DOWN', $payload['title']);
        $this->assertStringContainsString('Reminder #2: the incident has not been acknowledged yet.', $payload['text']);
        $this->assertStringContainsString('Down for: 20m', $payload['text']);
        $this->assertSame(2, $payload['incident']['reminder_count']);
        $this->assertNull($payload['incident']['acknowledged_at']);
    }

    public function test_a_queued_reminder_is_dropped_after_acknowledgement(): void
    {
        $this->fakeOutboundHttp();
        $incident = $this->openIncident(['acknowledged_at' => now()]);

        Bus::dispatchSync(new SendIncidentNotification($incident->id, $this->channel->id, NotificationType::Reminder, 1));

        $this->assertSame([], $this->outboundRequests);
    }

    public function test_reminder_interval_is_validated_against_the_configured_list(): void
    {
        $this->fakeDns(['shop.example.com' => ['93.184.215.30']]);
        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $this->patchJson("/api/monitors/{$this->monitor->id}", ['reminder_interval_minutes' => 7])->assertJsonValidationErrors('reminder_interval_minutes');
        $this->patchJson("/api/monitors/{$this->monitor->id}", ['reminder_interval_minutes' => 30])->assertOk()->assertJsonPath('data.reminder_interval_minutes', 30);
        $this->patchJson("/api/monitors/{$this->monitor->id}", ['reminder_interval_minutes' => null])->assertOk()->assertJsonPath('data.reminder_interval_minutes', null);
    }

    public function test_reminders_are_scheduled_every_minute(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'incidents:send-reminders'));

        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->onOneServer);
    }
}
