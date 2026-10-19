<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use App\Services\BadgeRenderer;
use App\Services\PublicStatusPagePresenter;
use App\Services\UptimeCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class PublicBadgeController extends Controller
{
    private const CACHE_SECONDS = 300;

    private const STATUS_COLORS = [
        'up' => '#15803d',
        'degraded' => '#a16207',
        'down' => '#dc2626',
        'maintenance' => '#6d28d9',
    ];

    private const NEUTRAL_COLOR = '#5f6876';

    public function __construct(private BadgeRenderer $renderer) {}

    public function status(string $badgeToken): Response
    {
        $monitor = Monitor::query()->where('badge_token', $badgeToken)->first();

        if ($monitor === null) {
            return $this->svg($this->renderer->render(__('status'), __('not found'), self::NEUTRAL_COLOR), 404);
        }

        $status = PublicStatusPagePresenter::publicStatus($monitor);

        return $this->svg($this->renderer->render(__('status'), __($status), self::STATUS_COLORS[$status] ?? self::NEUTRAL_COLOR));
    }

    public function uptime(Request $request, string $badgeToken, UptimeCalculator $uptimeCalculator): Response
    {
        $days = (int) ($request->validate(['days' => ['sometimes', 'integer', 'in:7,30,90']])['days'] ?? 30);
        $monitor = Monitor::query()->where('badge_token', $badgeToken)->first();
        $label = __('uptime :days d', ['days' => $days]);

        if ($monitor === null) {
            return $this->svg($this->renderer->render($label, __('not found'), self::NEUTRAL_COLOR), 404);
        }

        $percentage = Cache::remember("badge-uptime:{$monitor->id}:{$days}", self::CACHE_SECONDS, function () use ($monitor, $days, $uptimeCalculator): ?float {
            $history = $uptimeCalculator->daily($monitor, $days);

            return UptimeCalculator::percentage(array_sum(array_column($history, 'successful_checks')), array_sum(array_column($history, 'total_checks')));
        });

        return $this->svg($this->renderer->render(
            $label,
            $percentage === null ? __('no data') : rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.').'%',
            match (true) {
                $percentage === null => self::NEUTRAL_COLOR,
                $percentage >= 99.9 => self::STATUS_COLORS['up'],
                $percentage >= 95 => self::STATUS_COLORS['degraded'],
                default => self::STATUS_COLORS['down'],
            },
        ));
    }

    private function svg(string $content, int $status = 200): Response
    {
        return response($content, $status, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS,
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
        ]);
    }
}
