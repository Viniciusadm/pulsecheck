<?php

namespace App\Http\Controllers;

use App\Http\Resources\MonitorResource;
use App\Models\Monitor;
use Illuminate\Support\Str;

class MonitorBadgeTokenController extends Controller
{
    public function store(Monitor $monitor): MonitorResource
    {
        $monitor->forceFill(['badge_token' => Str::random(40)])->save();

        return new MonitorResource($monitor);
    }

    public function destroy(Monitor $monitor): MonitorResource
    {
        $monitor->forceFill(['badge_token' => null])->save();

        return new MonitorResource($monitor);
    }
}
