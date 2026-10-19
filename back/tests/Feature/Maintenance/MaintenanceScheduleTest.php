<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\Team;
use App\Services\MaintenanceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaintenanceScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function schedule(): MaintenanceSchedule
    {
        return new MaintenanceSchedule;
    }

    public function test_one_off_window_includes_its_start_and_excludes_its_end(): void
    {
        $window = MaintenanceWindow::factory()->make(['starts_at' => '2026-10-17 10:00:00', 'ends_at' => '2026-10-17 11:00:00']);

        $this->assertNull($this->schedule()->activeUntil($window, CarbonImmutable::parse('2026-10-17 09:59:59')));
        $this->assertSame('2026-10-17 11:00:00', $this->schedule()->activeUntil($window, CarbonImmutable::parse('2026-10-17 10:00:00'))?->toDateTimeString());
        $this->assertNull($this->schedule()->activeUntil($window, CarbonImmutable::parse('2026-10-17 11:00:00')));
    }

    public static function recurringCases(): array
    {
        return [
            'inside on a selected day' => [[6], '02:00', '04:00', 'UTC', '2026-10-17 03:00:00', '2026-10-17 04:00:00'],
            'same time on another day' => [[1], '02:00', '04:00', 'UTC', '2026-10-17 03:00:00', null],
            'end is exclusive' => [[6], '02:00', '04:00', 'UTC', '2026-10-17 04:00:00', null],
            'crosses midnight, before midnight' => [[6], '23:00', '01:00', 'UTC', '2026-10-17 23:30:00', '2026-10-18 01:00:00'],
            'crosses midnight, next day half' => [[6], '23:00', '01:00', 'UTC', '2026-10-18 00:30:00', '2026-10-18 01:00:00'],
            'next day half needs the start day' => [[7], '23:00', '01:00', 'UTC', '2026-10-18 00:30:00', null],
            'local time zone' => [[6], '02:00', '04:00', 'America/Sao_Paulo', '2026-10-17 06:00:00', '2026-10-17 07:00:00'],
            'local time zone, outside in UTC terms' => [[6], '02:00', '04:00', 'America/Sao_Paulo', '2026-10-17 03:00:00', null],
            'daylight saving change' => [[7], '01:00', '04:00', 'Europe/Berlin', '2026-10-25 02:30:00', '2026-10-25 03:00:00'],
        ];
    }

    #[DataProvider('recurringCases')]
    public function test_recurring_windows(array $weekdays, string $start, string $end, string $timezone, string $at, ?string $expectedEnd): void
    {
        $window = MaintenanceWindow::factory()->recurring($weekdays, $start, $end, $timezone)->make();

        $this->assertSame($expectedEnd, $this->schedule()->activeUntil($window, CarbonImmutable::parse($at, 'UTC'))?->toDateTimeString());
    }

    public function test_next_start_of_recurring_and_one_off_windows(): void
    {
        $at = CarbonImmutable::parse('2026-10-17 12:00:00');
        $weekly = MaintenanceWindow::factory()->recurring([1], '02:00', '03:00')->make();
        $oneOff = MaintenanceWindow::factory()->make(['starts_at' => '2026-10-20 08:00:00', 'ends_at' => '2026-10-20 09:00:00']);
        $past = MaintenanceWindow::factory()->make(['starts_at' => '2026-10-10 08:00:00', 'ends_at' => '2026-10-10 09:00:00']);

        $this->assertSame('2026-10-19 02:00:00', $this->schedule()->nextStart($weekly, $at)?->toDateTimeString());
        $this->assertSame('2026-10-20 08:00:00', $this->schedule()->nextStart($oneOff, $at)?->toDateTimeString());
        $this->assertNull($this->schedule()->nextStart($past, $at));
    }

    public function test_windows_apply_directly_or_through_teams_and_the_latest_end_wins(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00'));
        $team = Team::factory()->create();
        $monitor = Monitor::factory()->create();
        $monitor->teams()->attach($team);
        $other = Monitor::factory()->create();

        MaintenanceWindow::factory()->active()->create(['ends_at' => now()->addMinutes(30)])->monitors()->attach($monitor);
        MaintenanceWindow::factory()->active()->create(['ends_at' => now()->addHours(2)])->teams()->attach($team);
        MaintenanceWindow::factory()->create()->monitors()->attach($monitor);

        $this->assertTrue($this->schedule()->endsAtFor($monitor, now())->equalTo(now()->addHours(2)));
        $this->assertNull($this->schedule()->endsAtFor($other, now()));
    }
}
