<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = now()->subMinutes(fake()->numberBetween(30, 600));

        return [
            'monitor_id' => Monitor::factory(),
            'started_at' => $startedAt,
            'resolved_at' => $startedAt->copy()->addMinutes(fake()->numberBetween(1, 20)),
            'cause' => 'HTTP 503',
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolved_at' => null,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolved_at' => $attributes['started_at']->copy()->addMinutes(fake()->numberBetween(1, 20)),
        ]);
    }
}
