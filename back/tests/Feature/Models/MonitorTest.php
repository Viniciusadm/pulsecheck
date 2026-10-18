<?php

namespace Tests\Feature\Models;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_monitor_starts_pending_and_active(): void
    {
        $monitor = User::factory()->create()->monitors()->create([
            'name' => 'Example',
            'url' => 'https://example.com',
            'interval_minutes' => 5,
            'timeout_seconds' => 10,
        ]);

        $monitor->refresh();

        $this->assertSame(MonitorStatus::Pending, $monitor->status);
        $this->assertFalse($monitor->is_paused);
        $this->assertSame(0, $monitor->consecutive_failures);
        $this->assertSame(0, $monitor->consecutive_successes);
    }

    public function test_user_id_cannot_be_mass_assigned(): void
    {
        $monitor = new Monitor(['user_id' => 123, 'name' => 'Example']);

        $this->assertNull($monitor->user_id);
        $this->assertSame('Example', $monitor->name);
    }

    #[DataProvider('allowedIntervals')]
    public function test_database_accepts_allowed_intervals(int $interval): void
    {
        $monitor = Monitor::factory()->create(['interval_minutes' => $interval]);

        $this->assertSame($interval, $monitor->fresh()->interval_minutes);
    }

    public static function allowedIntervals(): array
    {
        return [[1], [5], [10]];
    }

    #[DataProvider('invalidIntervals')]
    public function test_database_rejects_intervals_outside_the_allowed_list(int $interval): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23514');

        Monitor::factory()->create(['interval_minutes' => $interval]);
    }

    public static function invalidIntervals(): array
    {
        return [[0], [2], [60]];
    }

    #[DataProvider('invalidTimeouts')]
    public function test_database_rejects_timeouts_outside_the_safe_range(int $timeout): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23514');

        Monitor::factory()->create(['timeout_seconds' => $timeout]);
    }

    public static function invalidTimeouts(): array
    {
        return [[0], [31]];
    }
}
