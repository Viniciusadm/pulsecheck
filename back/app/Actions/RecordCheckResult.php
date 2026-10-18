<?php

namespace App\Actions;

use App\Enums\Transition;
use App\Events\MonitorRecovered;
use App\Events\MonitorWentDown;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Services\CheckOutcome;
use App\Services\MonitorStateMachine;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordCheckResult
{
    private const MAX_TEXT_LENGTH = 500;

    public function __construct(private MonitorStateMachine $stateMachine) {}

    public function handle(Monitor $monitor, CheckOutcome $outcome): ?CheckResult
    {
        $checkedUrl = $monitor->url;

        return DB::transaction(function () use ($monitor, $outcome, $checkedUrl): ?CheckResult {
            $monitor = Monitor::query()->lockForUpdate()->find($monitor->id);

            if ($monitor === null || $monitor->url !== $checkedUrl) {
                return null;
            }

            $checkResult = $monitor->checkResults()->create([
                'started_at' => $outcome->startedAt,
                'finished_at' => $outcome->finishedAt,
                'latency_ms' => $outcome->latencyMs,
                'status_code' => $outcome->statusCode,
                'is_success' => $outcome->isSuccess,
                'error_type' => $outcome->errorType,
                'error_message' => $this->limit($outcome->errorMessage),
            ]);

            $transition = $monitor->is_paused ? null : $this->stateMachine->apply($monitor, $outcome);

            $monitor->forceFill([
                'last_status_code' => $outcome->statusCode,
                'last_latency_ms' => $outcome->latencyMs,
                'last_checked_at' => $outcome->finishedAt,
            ]);

            if (! $outcome->isSuccess) {
                $monitor->last_failed_at = $outcome->finishedAt;
            }

            $monitor->save();

            match ($transition) {
                Transition::WentDown => $this->openIncident($monitor, $outcome),
                Transition::Recovered => $this->resolveIncident($monitor),
                default => null,
            };

            return $checkResult;
        });
    }

    private function openIncident(Monitor $monitor, CheckOutcome $outcome): void
    {
        $incident = $monitor->incidents()->create([
            'started_at' => $this->streakStartedAt($monitor, $this->stateMachine->failuresToDown()),
            'cause' => $this->limit($outcome->errorType?->value.': '.$outcome->errorMessage),
        ]);

        MonitorWentDown::dispatch($monitor, $incident);
    }

    private function resolveIncident(Monitor $monitor): void
    {
        $incident = $monitor->incidents()->whereNull('resolved_at')->first();

        if ($incident === null) {
            return;
        }

        $incident->update(['resolved_at' => $this->streakStartedAt($monitor, $this->stateMachine->successesToUp())]);

        MonitorRecovered::dispatch($monitor, $incident);
    }

    private function streakStartedAt(Monitor $monitor, int $streakLength): CarbonInterface
    {
        return $monitor->checkResults()
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit($streakLength)
            ->get(['started_at'])
            ->min('started_at');
    }

    private function limit(?string $text): ?string
    {
        return $text === null ? null : Str::limit($text, self::MAX_TEXT_LENGTH - 3, '...');
    }
}
