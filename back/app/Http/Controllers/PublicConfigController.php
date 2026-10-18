<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class PublicConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'registration_enabled' => (bool) config('pulsecheck.registration_enabled'),
                'monitors' => [
                    'allowed_intervals' => config('pulsecheck.allowed_intervals'),
                    'default_interval' => config('pulsecheck.default_interval'),
                    'timeout_min' => config('pulsecheck.timeout_min'),
                    'timeout_max' => config('pulsecheck.timeout_max'),
                    'timeout_default' => config('pulsecheck.timeout_default'),
                    'max_per_user' => config('pulsecheck.max_monitors_per_user'),
                ],
            ],
        ]);
    }
}
