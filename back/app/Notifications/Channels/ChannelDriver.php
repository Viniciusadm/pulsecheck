<?php

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use App\Notifications\AlertMessage;

interface ChannelDriver
{
    public function rules(): array;

    public function secretKeys(): array;

    public function send(NotificationChannel $channel, AlertMessage $message): void;
}
