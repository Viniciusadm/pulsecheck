<?php

namespace App\Actions;

use App\Models\StatusPage;

class SyncStatusPageMonitors
{
    public function handle(StatusPage $statusPage, array $requested, array $visibleMonitorIds): void
    {
        $requested = array_values(array_filter($requested, fn (array $entry) => in_array($entry['monitor_id'], $visibleMonitorIds, true)));
        $current = $statusPage->monitors()->get(['monitors.id'])->map(fn ($monitor) => [
            'monitor_id' => $monitor->id,
            'display_name' => $monitor->pivot->display_name,
        ])->all();

        $slots = [];

        foreach ($current as $entry) {
            if (in_array($entry['monitor_id'], $visibleMonitorIds, true)) {
                $next = array_shift($requested);

                if ($next !== null) {
                    $slots[] = $next;
                }

                continue;
            }

            $slots[] = $entry;
        }

        $slots = [...$slots, ...$requested];

        $statusPage->monitors()->sync(collect($slots)->values()->mapWithKeys(fn (array $entry, int $position) => [
            $entry['monitor_id'] => ['sort_order' => $position, 'display_name' => $entry['display_name'] ?? null],
        ])->all());
    }
}
