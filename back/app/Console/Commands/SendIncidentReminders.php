<?php

namespace App\Console\Commands;

use App\Enums\IncidentKind;
use App\Enums\NotificationType;
use App\Jobs\SendIncidentNotification;
use App\Models\Incident;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('incidents:send-reminders')]
#[Description('Repeat the down alert of open, unacknowledged outages whose reminder interval has passed')]
class SendIncidentReminders extends Command
{
    private const DUE_CONDITION = 'coalesce(incidents.last_reminded_at, incidents.created_at) + make_interval(mins => monitors.reminder_interval_minutes) <= ?';

    public function handle(): int
    {
        $now = now();
        $sent = 0;

        $dueIncidentIds = Incident::query()
            ->join('monitors', 'monitors.id', '=', 'incidents.monitor_id')
            ->where('incidents.kind', IncidentKind::Outage)
            ->whereNull('incidents.resolved_at')
            ->whereNull('incidents.acknowledged_at')
            ->where('monitors.is_paused', false)
            ->whereNull('monitors.maintenance_ends_at')
            ->whereNotNull('monitors.reminder_interval_minutes')
            ->whereRaw(self::DUE_CONDITION, [$now])
            ->pluck('incidents.id');

        foreach ($dueIncidentIds as $incidentId) {
            $reminderCount = $this->claim($incidentId, $now);

            if ($reminderCount === null) {
                continue;
            }

            Incident::query()->findOrFail($incidentId)->monitor->notificationChannels()
                ->where('enabled', true)
                ->pluck('notification_channels.id')
                ->each(fn (int $channelId) => SendIncidentNotification::dispatch($incidentId, $channelId, NotificationType::Reminder, $reminderCount));

            $sent++;
        }

        $this->components->info("Sent {$sent} incident reminder(s).");

        return self::SUCCESS;
    }

    private function claim(int $incidentId, CarbonInterface $now): ?int
    {
        $row = DB::selectOne(
            'UPDATE incidents SET last_reminded_at = ?, reminder_count = reminder_count + 1, updated_at = ?
             FROM monitors
             WHERE incidents.id = ? AND monitors.id = incidents.monitor_id
               AND incidents.resolved_at IS NULL AND incidents.acknowledged_at IS NULL
               AND monitors.reminder_interval_minutes IS NOT NULL
               AND '.self::DUE_CONDITION.'
             RETURNING incidents.reminder_count',
            [$now, $now, $incidentId, $now],
        );

        return $row === null ? null : (int) $row->reminder_count;
    }
}
