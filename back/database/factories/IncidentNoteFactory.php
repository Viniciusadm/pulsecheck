<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentNote>
 */
class IncidentNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
