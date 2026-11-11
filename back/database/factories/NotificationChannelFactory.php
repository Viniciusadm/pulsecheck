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

    public function microsoftTeams(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NotificationChannelType::MicrosoftTeams,
            'config' => ['webhook_url' => 'https://prod-01.westus.logic.azure.com:443/workflows/'.fake()->regexify('[a-f0-9]{32}').'/triggers/manual/paths/invoke?sig='.fake()->regexify('[A-Za-z0-9]{40}')],
        ]);
    }

    public function googleChat(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NotificationChannelType::GoogleChat,
            'config' => ['webhook_url' => 'https://chat.googleapis.com/v1/spaces/AAAA'.fake()->regexify('[A-Za-z0-9]{7}').'/messages?key='.fake()->regexify('[A-Za-z0-9]{39}').'&token='.fake()->regexify('[A-Za-z0-9]{40}')],
        ]);
    }

    public function ntfy(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NotificationChannelType::Ntfy,
            'config' => ['topic' => 'pulsecheck-'.fake()->regexify('[a-z0-9]{16}')],
        ]);
    }

    public function pushover(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NotificationChannelType::Pushover,
            'config' => ['app_token' => fake()->regexify('[a-z0-9]{30}'), 'user_key' => fake()->regexify('[a-z0-9]{30}')],
        ]);
    }

    public function pagerDuty(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NotificationChannelType::PagerDuty,
            'config' => ['routing_key' => fake()->regexify('[a-z0-9]{32}')],
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
