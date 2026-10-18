<?php

namespace App\Enums;

use App\Notifications\Channels\ChannelDriver;
use App\Notifications\Channels\DiscordDriver;
use App\Notifications\Channels\EmailDriver;
use App\Notifications\Channels\SlackDriver;
use App\Notifications\Channels\TelegramDriver;
use App\Notifications\Channels\WebhookDriver;

enum NotificationChannelType: string
{
    case Telegram = 'telegram';
    case Webhook = 'webhook';
    case Slack = 'slack';
    case Discord = 'discord';
    case Email = 'email';

    public function driver(): ChannelDriver
    {
        return app(match ($this) {
            self::Telegram => TelegramDriver::class,
            self::Webhook => WebhookDriver::class,
            self::Slack => SlackDriver::class,
            self::Discord => DiscordDriver::class,
            self::Email => EmailDriver::class,
        });
    }
}
