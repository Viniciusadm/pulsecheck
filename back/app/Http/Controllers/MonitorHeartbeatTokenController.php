<?php

namespace App\Http\Controllers;

use App\Enums\MonitorType;
use App\Http\Resources\MonitorResource;
use App\Models\Monitor;
use Illuminate\Support\Str;

class MonitorHeartbeatTokenController extends Controller
{
    public function __invoke(Monitor $monitor): MonitorResource
    {
        abort_unless($monitor->type === MonitorType::Heartbeat, 404);

        $monitor->forceFill(['heartbeat_token' => Str::random(40)])->save();

        return new MonitorResource($monitor);
    }
}
