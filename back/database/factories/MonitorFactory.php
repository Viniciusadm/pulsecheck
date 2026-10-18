<?php

namespace Database\Factories;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
class MonitorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'url' => 'https://'.fake()->domainName(),
            'interval_minutes' => fake()->randomElement([1, 5, 10]),
            'timeout_seconds' => 10,
            'status' => MonitorStatus::Pending,
            'is_paused' => false,
        ];
    }

    public function up(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MonitorStatus::Up,
            'consecutive_successes' => 3,
            'last_status_code' => 200,
            'last_latency_ms' => fake()->numberBetween(50, 800),
            'last_checked_at' => now(),
        ]);
    }

    public function down(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MonitorStatus::Down,
            'consecutive_failures' => 3,
            'last_status_code' => 503,
            'last_checked_at' => now(),
            'last_failed_at' => now(),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_paused' => true,
        ]);
    }
}
