<?php

namespace App\Events;

use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidentUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Incident $incident) {}

    public function broadcastOn(): array
    {
        return User::query()
            ->whoCanSee($this->incident->monitor)
            ->pluck('id')
            ->map(fn (int $userId) => new PrivateChannel("users.{$userId}.monitors"))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'incident.updated';
    }

    public function broadcastWith(): array
    {
        $incident = $this->incident->loadMissing(['monitor:id,name', 'acknowledger:id,name'])->loadCount('notes');

        return ['incident' => IncidentResource::make($incident)->resolve()];
    }
}
