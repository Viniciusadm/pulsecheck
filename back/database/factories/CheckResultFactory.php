<?php

namespace Database\Factories;

use App\Enums\CheckErrorType;
use App\Models\CheckResult;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckResult>
 */
class CheckResultFactory extends Factory
{
    public function definition(): array
    {
        $latencyMs = fake()->numberBetween(50, 800);
        $startedAt = now()->subSeconds(fake()->numberBetween(60, 3600));

        return [
            'monitor_id' => Monitor::factory(),
            'started_at' => $startedAt,
            'finished_at' => $startedAt->copy()->addMilliseconds($latencyMs),
            'latency_ms' => $latencyMs,
            'status_code' => 200,
            'is_success' => true,
            'error_type' => null,
            'error_message' => null,
        ];
    }

    public function failed(CheckErrorType $errorType = CheckErrorType::HttpStatus): static
    {
        return $this->state(fn (array $attributes) => [
            'is_success' => false,
            'status_code' => $errorType === CheckErrorType::HttpStatus ? 503 : null,
            'latency_ms' => $errorType === CheckErrorType::HttpStatus ? $attributes['latency_ms'] : null,
            'error_type' => $errorType,
            'error_message' => $errorType === CheckErrorType::HttpStatus ? 'HTTP 503' : 'Check failed',
        ]);
    }
}
