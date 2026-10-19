<?php

namespace App\Http\Controllers;

use App\Actions\RecordHeartbeat;
use App\Enums\MonitorType;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;

class HeartbeatController extends Controller
{
    public function __invoke(string $token, RecordHeartbeat $recordHeartbeat): JsonResponse
    {
        $monitor = Monitor::query()
            ->where('type', MonitorType::Heartbeat)
            ->where('heartbeat_token', $token)
            ->firstOrFail();

        return response()->json(['data' => ['accepted' => $recordHeartbeat->handle($monitor)]]);
    }
}
