<?php

namespace Database\Factories;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationChannel>
 */
class NotificationChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => NotificationChannelType::Webhook,
            'name' => fake()->words(2, true),
            'config' => ['url' => 'https://hooks.example.com/'.fake()->uuid(), 'headers' => []],
            'enabled' => true,
            'is_default' => false,
        ];
    }

    public function telegram(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NotificationChannelType::Telegram,
            'config' => [
                'bot_token' => fake()->numerify('#########').':'.fake()->regexify('[A-Za-z0-9_-]{35}'),
                'chat_id' => fake()->numerify('#########'),
            ],
        ]);
    }

    public function webhook(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NotificationChannelType::Webhook,
            'config' => ['url' => 'https://hooks.example.com/'.fake()->uuid(), 'headers' => []],
        ]);
    }

    public function slack(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NotificationChannelType::Slack,
            'config' => ['webhook_url' => 'https://hooks.slack.com/services/T000/B000/'.fake()->regexify('[A-Za-z0-9]{24}')],
        ]);
    }

    public function discord(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NotificationChannelType::Discord,
            'config' => ['webhook_url' => 'https://discord.com/api/webhooks/'.fake()->numerify('##########').'/'.fake()->regexify('[A-Za-z0-9_-]{40}')],
        ]);
    }

    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NotificationChannelType::Email,
            'config' => ['email' => fake()->safeEmail()],
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => ['enabled' => false]);
    }

    public function asDefault(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }
}
