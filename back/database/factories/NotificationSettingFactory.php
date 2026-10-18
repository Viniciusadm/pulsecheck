<?php

namespace Database\Factories;

use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationSetting>
 */
class NotificationSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'telegram_bot_token' => null,
            'telegram_chat_id' => null,
            'telegram_enabled' => false,
        ];
    }

    public function withTelegram(): static
    {
        return $this->state(fn (array $attributes) => [
            'telegram_bot_token' => fake()->numerify('#########').':'.fake()->regexify('[A-Za-z0-9_-]{35}'),
            'telegram_chat_id' => fake()->numerify('#########'),
            'telegram_enabled' => true,
        ]);
    }
}
