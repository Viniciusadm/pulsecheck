<?php

namespace App\Events;

use App\Http\Resources\MonitorResource;
use App\Models\Monitor;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MonitorChecked implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Monitor $monitor) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('monitors');
    }

    public function broadcastAs(): string
    {
        return 'monitor.checked';
    }

    public function broadcastWith(): array
    {
        return ['monitor' => MonitorResource::make($this->monitor)->resolve()];
    }
}
