<?php

namespace App\Http\Controllers;

use App\Actions\ResumeMonitor;
use App\Http\Resources\MonitorResource;
use App\Models\Monitor;

class MonitorResumeController extends Controller
{
    public function __invoke(Monitor $monitor, ResumeMonitor $resumeMonitor): MonitorResource
    {
        return new MonitorResource($resumeMonitor->handle($monitor));
    }
}
