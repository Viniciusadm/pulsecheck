<?php

namespace App\Http\Controllers;

use App\Models\InstanceSettings;
use Illuminate\Http\JsonResponse;

class PublicConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $setupToken = config('pulsecheck.setup_token');

        return response()->json([
            'data' => [
                'setup_required' => InstanceSettings::current() === null,
                'setup_token_required' => filled($setupToken),
                'instance' => [
                    'name' => InstanceSettings::currentName(),
                    'timezone' => InstanceSettings::currentTimezone(),
                    'locale' => InstanceSettings::currentLocale(),
                ],
                'available_locales' => config('pulsecheck.locales'),
                'monitors' => [
                    'allowed_intervals' => config('pulsecheck.allowed_intervals'),
                    'default_interval' => config('pulsecheck.default_interval'),
                    'timeout_min' => config('pulsecheck.timeout_min'),
                    'timeout_max' => config('pulsecheck.timeout_max'),
                    'timeout_default' => config('pulsecheck.timeout_default'),
                ],
                'realtime' => config('broadcasting.default') === 'reverb'
                    ? ['key' => config('broadcasting.connections.reverb.key')]
                    : null,
            ],
        ]);
    }
}
