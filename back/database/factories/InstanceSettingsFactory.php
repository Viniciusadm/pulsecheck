<?php

namespace Database\Factories;

use App\Models\InstanceSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstanceSettings>
 */
class InstanceSettingsFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'timezone' => 'UTC',
            'locale' => 'en',
        ];
    }
}
