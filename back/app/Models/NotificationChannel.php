<?php

namespace App\Models;

use App\Enums\MessageFormat;
use App\Enums\NotificationChannelType;
use App\Enums\NotificationType;
use App\Models\Concerns\BelongsToTeams;
use Carbon\CarbonInterface;
use Database\Factories\NotificationChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['type', 'name', 'config', 'enabled', 'is_default', 'events', 'quiet_hours_start', 'quiet_hours_end', 'quiet_hours_allow_critical', 'message_format'])]
#[Hidden(['config'])]
class NotificationChannel extends Model
{
    /** @use HasFactory<NotificationChannelFactory> */
    use BelongsToTeams, HasFactory;

    protected $attributes = [
        'enabled' => true,
        'is_default' => false,
        'quiet_hours_allow_critical' => true,
        'message_format' => 'detailed',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationChannelType::class,
            'config' => 'encrypted:array',
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'events' => 'array',
            'quiet_hours_allow_critical' => 'boolean',
            'message_format' => MessageFormat::class,
        ];
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class);
    }

    public function setting(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }

    public function subscribedEvents(): array
    {
        return $this->events ?? array_column(NotificationType::cases(), 'value');
    }

    public function receives(NotificationType $type): bool
    {
        return in_array($type->value, $this->subscribedEvents(), true);
    }

    public function hasQuietHours(): bool
    {
        return $this->quiet_hours_start !== null && $this->quiet_hours_end !== null;
    }

    public function isQuietAt(CarbonInterface $moment): bool
    {
        if (! $this->hasQuietHours()) {
            return false;
        }

        $time = $moment->copy()->setTimezone(InstanceSettings::currentTimezone())->format('H:i');
        $start = substr($this->quiet_hours_start, 0, 5);
        $end = substr($this->quiet_hours_end, 0, 5);

        return $start <= $end
            ? $time >= $start && $time < $end
            : $time >= $start || $time < $end;
    }
}
