<?php

namespace App\Http\Controllers;

use App\Models\InstanceSettings;
use App\Models\StatusPage;
use App\Support\SetupToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicConfigController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $statusPage = $request->attributes->get(StatusPage::class);

        return response()->json([
            'data' => [
                'setup_required' => InstanceSettings::current() === null,
                'setup_token_required' => SetupToken::current() !== null,
                'instance' => [
                    'name' => InstanceSettings::currentName(),
                    'timezone' => InstanceSettings::currentTimezone(),
                    'locale' => InstanceSettings::currentLocale(),
                ],
                'available_locales' => config('pulsecheck.locales'),
                'monitors' => [
                    'allowed_intervals' => config('pulsecheck.allowed_intervals'),
                    'heartbeat_intervals' => config('pulsecheck.heartbeat_allowed_intervals'),
                    'keyword_max_body_bytes' => config('pulsecheck.keyword_max_body_bytes'),
                    'reminder_intervals' => config('pulsecheck.reminder_intervals'),
                    'default_interval' => config('pulsecheck.default_interval'),
                    'timeout_min' => config('pulsecheck.timeout_min'),
                    'timeout_max' => config('pulsecheck.timeout_max'),
                    'timeout_default' => config('pulsecheck.timeout_default'),
                ],
                'realtime' => config('broadcasting.default') === 'reverb'
                    ? ['key' => config('broadcasting.connections.reverb.key')]
                    : null,
                'status_page_domain' => $statusPage instanceof StatusPage ? ['slug' => $statusPage->slug] : null,
            ],
        ]);
    }
}
