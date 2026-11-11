<?php

namespace App\Enums;

use App\Notifications\Channels\ChannelDriver;
use App\Notifications\Channels\DiscordDriver;
use App\Notifications\Channels\EmailDriver;
use App\Notifications\Channels\GoogleChatDriver;
use App\Notifications\Channels\MicrosoftTeamsDriver;
use App\Notifications\Channels\NtfyDriver;
use App\Notifications\Channels\PagerDutyDriver;
use App\Notifications\Channels\PushoverDriver;
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
    case MicrosoftTeams = 'microsoft_teams';
    case GoogleChat = 'google_chat';
    case Ntfy = 'ntfy';
    case Pushover = 'pushover';
    case PagerDuty = 'pagerduty';

    public function driver(): ChannelDriver
    {
        return app(match ($this) {
            self::Telegram => TelegramDriver::class,
            self::Webhook => WebhookDriver::class,
            self::Slack => SlackDriver::class,
            self::Discord => DiscordDriver::class,
            self::Email => EmailDriver::class,
            self::MicrosoftTeams => MicrosoftTeamsDriver::class,
            self::GoogleChat => GoogleChatDriver::class,
            self::Ntfy => NtfyDriver::class,
            self::Pushover => PushoverDriver::class,
            self::PagerDuty => PagerDutyDriver::class,
        });
    }
}
