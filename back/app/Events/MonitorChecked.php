<?php

namespace App\Events;

use App\Http\Resources\MonitorResource;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

class MonitorChecked implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Monitor $monitor) {}

    public function broadcastOn(): array
    {
        return User::query()
            ->whoCanSee($this->monitor)
            ->pluck('id')
            ->map(fn (int $userId) => new PrivateChannel("users.{$userId}.monitors"))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'monitor.checked';
    }

    public function broadcastWith(): array
    {
        return ['monitor' => MonitorResource::make($this->monitor)->resolve(new Request)];
    }
}
