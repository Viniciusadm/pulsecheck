<?php

namespace Database\Factories;

use App\Enums\MaintenanceWindowType;
use App\Models\MaintenanceWindow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceWindow>
 */
class MaintenanceWindowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => MaintenanceWindowType::OneOff,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MaintenanceWindowType::OneOff,
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addHour(),
        ]);
    }

    public function recurring(array $weekdays, string $startTime, string $endTime, string $timezone = 'UTC'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MaintenanceWindowType::Recurring,
            'starts_at' => null,
            'ends_at' => null,
            'weekdays' => $weekdays,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'timezone' => $timezone,
        ]);
    }
}
