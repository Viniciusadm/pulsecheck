<?php

namespace App\Http\Controllers;

use App\Actions\PauseMonitor;
use App\Http\Resources\MonitorResource;
use App\Models\Monitor;

class MonitorPauseController extends Controller
{
    public function __invoke(Monitor $monitor, PauseMonitor $pauseMonitor): MonitorResource
    {
        return new MonitorResource($pauseMonitor->handle($monitor));
    }
}
