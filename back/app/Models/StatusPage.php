<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeams;
use Database\Factories\StatusPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'description', 'custom_domain', 'is_published', 'show_latency', 'show_incidents', 'uptime_days'])]
class StatusPage extends Model
{
    /** @use HasFactory<StatusPageFactory> */
    use BelongsToTeams, HasFactory;

    protected $attributes = [
        'is_published' => false,
        'show_latency' => false,
        'show_incidents' => true,
        'uptime_days' => 90,
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'show_latency' => 'boolean',
            'show_incidents' => 'boolean',
            'uptime_days' => 'integer',
        ];
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class)
            ->withPivot('sort_order', 'display_name')
            ->orderByPivot('sort_order');
    }

    public static function forHost(string $host): ?self
    {
        $host = mb_strtolower(rtrim($host, '.'));

        if ($host === '' || $host === self::applicationHost()) {
            return null;
        }

        return static::query()->where('custom_domain', $host)->first();
    }

    public static function applicationHost(): string
    {
        return mb_strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    public function publicUrl(): string
    {
        if ($this->custom_domain === null) {
            return rtrim((string) config('app.url'), '/')."/status/{$this->slug}";
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return "{$scheme}://{$this->custom_domain}";
    }

    public function cacheKey(): string
    {
        return "status-page:{$this->id}:{$this->updated_at?->getTimestamp()}";
    }
}
