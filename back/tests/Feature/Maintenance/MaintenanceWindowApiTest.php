<?php

namespace Tests\Feature\Maintenance;

use App\Enums\NotificationType;
use App\Jobs\SendIncidentNotification;
use App\Models\Incident;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaintenanceWindowApiTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Monitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00'));
        $this->team = Team::factory()->create();
        $this->monitor = Monitor::factory()->up()->create();
        $this->monitor->teams()->attach($this->team);
    }

    private function teamUser(bool $isManager = true): User
    {
        $user = $isManager ? User::factory()->manager()->create() : User::factory()->create();
        $user->teams()->attach($this->team);

        return $user;
    }

    private function oneOff(array $overrides = []): array
    {
        return [
            'name' => 'Database upgrade',
            'type' => 'one_off',
            'starts_at' => '2026-10-17T11:30:00Z',
            'ends_at' => '2026-10-17T14:00:00Z',
            'monitor_ids' => [$this->monitor->id],
            ...$overrides,
        ];
    }

    public function test_manager_creates_an_active_window_and_the_monitor_enters_maintenance(): void
    {
        $this->actingAs($this->teamUser())->fromSpa()
            ->postJson('/api/maintenance-windows', $this->oneOff())
            ->assertCreated()
            ->assertJsonPath('data.monitor_ids', [$this->monitor->id])
            ->assertJsonPath('data.monitors.0.name', $this->monitor->name)
            ->assertJsonPath('data.active_until', '2026-10-17T14:00:00.000000Z');

        $this->assertTrue($this->monitor->fresh()->maintenance_ends_at->equalTo(CarbonImmutable::parse('2026-10-17 14:00:00')));
    }

    public function test_recurring_window_is_stored_with_its_schedule(): void
    {
        $this->actingAs($this->teamUser())->fromSpa()
            ->postJson('/api/maintenance-windows', [
                'name' => 'Nightly backup',
                'type' => 'recurring',
                'weekdays' => [1, 3, 5],
                'start_time' => '23:30',
                'end_time' => '00:30',
                'timezone' => 'America/Sao_Paulo',
                'team_ids' => [$this->team->id],
                'starts_at' => '2026-10-17T11:30:00Z',
            ])
            ->assertCreated()
            ->assertJsonPath('data.weekdays', [1, 3, 5])
            ->assertJsonPath('data.start_time', '23:30')
            ->assertJsonPath('data.starts_at', null)
            ->assertJsonPath('data.team_ids', [$this->team->id])
            ->assertJsonPath('data.next_starts_at', '2026-10-20T02:30:00.000000Z');
    }

    public static function invalidPayloads(): array
    {
        return [
            'end before start' => [['ends_at' => '2026-10-17T11:00:00Z'], 'ends_at'],
            'longer than 31 days' => [['ends_at' => '2026-12-01T00:00:00Z'], 'ends_at'],
            'no target' => [['monitor_ids' => []], 'monitor_ids'],
            'unknown type' => [['type' => 'monthly'], 'type'],
            'recurring without weekdays' => [['type' => 'recurring', 'start_time' => '01:00', 'end_time' => '02:00', 'timezone' => 'UTC'], 'weekdays'],
            'recurring with same times' => [['type' => 'recurring', 'weekdays' => [1], 'start_time' => '01:00', 'end_time' => '01:00', 'timezone' => 'UTC'], 'end_time'],
            'bad timezone' => [['type' => 'recurring', 'weekdays' => [1], 'start_time' => '01:00', 'end_time' => '02:00', 'timezone' => 'Mars/Base'], 'timezone'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_windows_are_rejected(array $overrides, string $field): void
    {
        $this->actingAs($this->teamUser())->fromSpa()
            ->postJson('/api/maintenance-windows', $this->oneOff($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_members_can_list_but_not_change_windows(): void
    {
        $window = MaintenanceWindow::factory()->create();
        $window->monitors()->attach($this->monitor);
        $member = $this->teamUser(isManager: false);

        $this->actingAs($member)->fromSpa()->getJson('/api/maintenance-windows')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($member)->fromSpa()->postJson('/api/maintenance-windows', $this->oneOff())->assertForbidden();
        $this->actingAs($member)->fromSpa()->deleteJson("/api/maintenance-windows/{$window->id}")->assertForbidden();
    }

    public function test_windows_of_other_teams_are_hidden_and_their_monitors_rejected(): void
    {
        $otherMonitor = Monitor::factory()->create();
        $otherMonitor->teams()->attach(Team::factory()->create());
        $hidden = MaintenanceWindow::factory()->create();
        $hidden->monitors()->attach($otherMonitor);
        $manager = $this->teamUser();

        $this->actingAs($manager)->fromSpa()->getJson('/api/maintenance-windows')->assertJsonCount(0, 'data');
        $this->actingAs($manager)->fromSpa()->getJson("/api/maintenance-windows/{$hidden->id}")->assertNotFound();
        $this->actingAs($manager)->fromSpa()
            ->postJson('/api/maintenance-windows', $this->oneOff(['monitor_ids' => [$otherMonitor->id]]))
            ->assertJsonValidationErrors('monitor_ids.0');
    }

    public function test_updating_keeps_monitors_outside_the_users_scope(): void
    {
        $otherMonitor = Monitor::factory()->create();
        $otherMonitor->teams()->attach(Team::factory()->create());
        $window = MaintenanceWindow::factory()->create();
        $window->monitors()->attach([$this->monitor->id, $otherMonitor->id]);

        $this->actingAs($this->teamUser())->fromSpa()
            ->putJson("/api/maintenance-windows/{$window->id}", $this->oneOff(['monitor_ids' => []]))
            ->assertOk()
            ->assertJsonPath('data.monitor_ids', []);

        $this->assertSame([$otherMonitor->id], $window->monitors()->pluck('monitors.id')->all());
    }

    public function test_filtering_by_monitor_includes_team_windows(): void
    {
        MaintenanceWindow::factory()->create()->teams()->attach($this->team);
        MaintenanceWindow::factory()->create()->monitors()->attach(Monitor::factory()->create());

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson("/api/maintenance-windows?monitor_id={$this->monitor->id}")
            ->assertJsonCount(1, 'data');
    }

    public function test_deleting_an_active_window_ends_the_maintenance_and_rechecks_failing_monitors(): void
    {
        $window = MaintenanceWindow::factory()->active()->create();
        $window->monitors()->attach($this->monitor);
        $this->monitor->forceFill(['maintenance_ends_at' => $window->ends_at, 'consecutive_failures' => 3, 'next_check_at' => now()->addMinutes(5)])->save();

        $this->actingAs($this->teamUser())->fromSpa()->deleteJson("/api/maintenance-windows/{$window->id}")->assertNoContent();

        $monitor = $this->monitor->fresh();
        $this->assertNull($monitor->maintenance_ends_at);
        $this->assertTrue($monitor->next_check_at->equalTo(now()));
    }

    public function test_sync_command_starts_and_ends_maintenance_on_schedule(): void
    {
        $window = MaintenanceWindow::factory()->create(['starts_at' => '2026-10-17 12:05:00', 'ends_at' => '2026-10-17 12:30:00']);
        $window->monitors()->attach($this->monitor);

        $this->artisan('maintenance:sync')->assertSuccessful();
        $this->assertNull($this->monitor->fresh()->maintenance_ends_at);

        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:05:00'));
        $this->artisan('maintenance:sync')->assertSuccessful();
        $this->assertNotNull($this->monitor->fresh()->maintenance_ends_at);

        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:30:00'));
        $this->artisan('maintenance:sync')->assertSuccessful();
        $this->assertNull($this->monitor->fresh()->maintenance_ends_at);
    }

    public function test_reminders_pause_during_maintenance(): void
    {
        Queue::fake();
        $this->monitor->forceFill(['reminder_interval_minutes' => 15, 'maintenance_ends_at' => now()->addHour()])->save();
        Incident::factory()->for($this->monitor)->open()->create(['created_at' => now()->subHour()]);

        $this->artisan('incidents:send-reminders')->assertSuccessful();

        Queue::assertNotPushed(SendIncidentNotification::class, fn (SendIncidentNotification $job) => $job->type === NotificationType::Reminder);
    }

    public function test_sync_is_scheduled_every_minute(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'maintenance:sync'));

        $this->assertSame('* * * * *', $event->expression);
    }
}
