<?php

namespace Database\Seeders;

use App\Enums\CheckErrorType;
use App\Enums\MonitorStatus;
use App\Models\CheckResult;
use App\Models\InstanceSettings;
use App\Models\Monitor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

class DevelopmentSeeder extends Seeder
{
    private const HISTORY_DAYS = 7;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DevelopmentSeeder must not run in production.');
        }

        mt_srand(2026);

        InstanceSettings::factory()->create([
            'name' => 'PulseCheck Demo',
            'timezone' => 'UTC',
        ]);

        User::factory()->admin()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
        ]);

        $now = CarbonImmutable::now()->startOfMinute();

        $website = $this->monitor('Company website', 'https://example.com', 5);
        $this->seedHistory($website, $now, latencyBase: 140, outages: [[$now->subDays(3)->setTime(14, 10), 4]]);

        $api = $this->monitor('Public API', 'https://api.example.com/health', 1);
        $this->seedHistory($api, $now, latencyBase: 90, outages: [[$now->subDay()->setTime(2, 40), 12]], days: 2);

        $docs = $this->monitor('Documentation', 'https://docs.example.com', 10);
        $this->seedHistory($docs, $now, latencyBase: 320, outages: []);

        $shop = $this->monitor('Shop checkout', 'https://shop.example.com/checkout', 5);
        $this->seedHistory($shop, $now, latencyBase: 210, outages: [[$now->subMinutes(25), 6]]);

        $staging = $this->monitor('Staging', 'https://staging.example.com', 10);
        $this->seedHistory($staging, $now->subDays(2), latencyBase: 260, outages: [], days: 3);
        $staging->forceFill(['is_paused' => true, 'next_check_at' => null])->save();
    }

    private function monitor(string $name, string $url, int $intervalMinutes): Monitor
    {
        return Monitor::factory()->create([
            'name' => $name,
            'url' => $url,
            'interval_minutes' => $intervalMinutes,
            'timeout_seconds' => 10,
            'next_check_at' => now(),
        ]);
    }

    private function seedHistory(Monitor $monitor, CarbonImmutable $until, int $latencyBase, array $outages, int $days = self::HISTORY_DAYS): void
    {
        $rows = [];
        $openIncident = null;
        $failures = 0;
        $successes = 0;
        $status = MonitorStatus::Pending;
        $lastResult = null;

        for ($startedAt = $until->subDays($days); $startedAt->lessThan($until); $startedAt = $startedAt->addMinutes($monitor->interval_minutes)) {
            $isSuccess = ! $this->isDuringOutage($startedAt, $outages, $monitor->interval_minutes);
            $latency = $isSuccess ? $this->latency($startedAt, $latencyBase) : null;

            $rows[] = [
                'monitor_id' => $monitor->id,
                'started_at' => $startedAt,
                'finished_at' => $startedAt->addMilliseconds($latency ?? 10_000),
                'latency_ms' => $latency,
                'status_code' => $isSuccess ? 200 : null,
                'is_success' => $isSuccess,
                'error_type' => $isSuccess ? null : CheckErrorType::Timeout->value,
                'error_message' => $isSuccess ? null : 'Operation timed out after 10000 milliseconds with 0 bytes received',
            ];

            $failures = $isSuccess ? 0 : $failures + 1;
            $successes = $isSuccess ? $successes + 1 : 0;

            if ($failures === 2 && $status !== MonitorStatus::Down) {
                $status = MonitorStatus::Down;
                $openIncident = $monitor->incidents()->create([
                    'started_at' => $startedAt->subMinutes($monitor->interval_minutes),
                    'cause' => 'timeout: Operation timed out after 10000 milliseconds with 0 bytes received',
                ]);
            }

            if ($successes === 2 && $status !== MonitorStatus::Up) {
                $openIncident?->update(['resolved_at' => $startedAt->subMinutes($monitor->interval_minutes)]);
                $openIncident = null;
                $status = MonitorStatus::Up;
            }

            $lastResult = end($rows);
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            CheckResult::insert($chunk);
        }

        $monitor->forceFill([
            'status' => $status,
            'consecutive_failures' => $failures,
            'consecutive_successes' => min($successes, 32767),
            'last_status_code' => $lastResult['status_code'],
            'last_latency_ms' => $lastResult['latency_ms'],
            'last_checked_at' => $lastResult['finished_at'],
            'last_failed_at' => collect($rows)->where('is_success', false)->last()['finished_at'] ?? null,
        ])->save();
    }

    private function isDuringOutage(CarbonImmutable $moment, array $outages, int $intervalMinutes): bool
    {
        foreach ($outages as [$start, $checks]) {
            if ($moment->greaterThanOrEqualTo($start) && $moment->lessThan($start->addMinutes($checks * $intervalMinutes))) {
                return true;
            }
        }

        return false;
    }

    private function latency(CarbonImmutable $moment, int $base): int
    {
        $dailyWave = sin(($moment->hour + $moment->minute / 60) / 24 * 2 * M_PI) * $base * 0.25;
        $noise = mt_rand(-15, 15) / 100 * $base;
        $spike = mt_rand(1, 100) > 98 ? $base * 1.5 : 0;

        return (int) max(20, round($base + $dailyWave + $noise + $spike));
    }
}
