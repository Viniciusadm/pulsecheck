<?php

namespace App\Actions;

use App\Enums\IncidentKind;
use App\Enums\Transition;
use App\Events\MonitorChecked;
use App\Events\MonitorDegradationResolved;
use App\Events\MonitorDegraded;
use App\Events\MonitorRecovered;
use App\Events\MonitorWentDown;
use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use App\Services\CheckOutcome;
use App\Services\MonitorStateMachine;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordCheckResult
{
    private const MAX_TEXT_LENGTH = 500;

    private const MAX_WARNING_LENGTH = 255;

    private const MIN_CONFIRMATION_DELAY_SECONDS = 60;

    public function __construct(private MonitorStateMachine $stateMachine) {}

    public function handle(Monitor $monitor, CheckOutcome $outcome): ?CheckResult
    {
        $checkedRevision = $monitor->config_revision;

        $checkResult = DB::transaction(function () use ($monitor, $outcome, $checkedRevision): ?CheckResult {
            $monitor = Monitor::query()->lockForUpdate()->find($monitor->id);

            if ($monitor === null || $monitor->config_revision !== $checkedRevision) {
                return null;
            }

            $checkResult = $monitor->checkResults()->create([
                'started_at' => $outcome->startedAt,
                'finished_at' => $outcome->finishedAt,
                'latency_ms' => $outcome->latencyMs,
                'status_code' => $outcome->statusCode,
                'is_success' => $outcome->isSuccess,
                'is_degraded' => $outcome->isDegraded,
                'error_type' => $outcome->errorType,
                'error_message' => $this->limit($outcome->errorMessage),
                'warning_type' => $outcome->warningType,
                'warning_message' => $outcome->warningMessage === null ? null : Str::limit($outcome->warningMessage, self::MAX_WARNING_LENGTH - 3, '...'),
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

            if ($outcome->certificateExpiresAt !== null) {
                $monitor->certificate_expires_at = $outcome->certificateExpiresAt;
            }

            if (! $monitor->is_paused && ! $monitor->type->isPassive() && $this->awaitsConfirmation($monitor, $outcome)) {
                $this->scheduleConfirmationCheck($monitor);
            }

            $monitor->save();

            match ($transition) {
                Transition::WentDown => $this->openOutage($monitor, $outcome),
                Transition::Recovered => $this->resolveOutage($monitor),
                Transition::BecameDegraded => $this->openDegradation($monitor, $outcome),
                Transition::DegradationResolved => $this->resolveDegradation($monitor),
                default => null,
            };

            return $checkResult->setRelation('monitor', $monitor);
        });

        if ($checkResult !== null) {
            rescue(fn () => MonitorChecked::dispatch($checkResult->monitor));
        }

        return $checkResult;
    }

    private function openOutage(Monitor $monitor, CheckOutcome $outcome): void
    {
        $monitor->incidents()
            ->where('kind', IncidentKind::Degradation)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => $outcome->startedAt, 'updated_at' => now()]);

        $incident = $monitor->incidents()->create([
            'kind' => IncidentKind::Outage,
            'started_at' => $this->streakStartedAt($monitor, $this->stateMachine->failuresToDown($monitor)),
            'cause' => $this->limit($outcome->errorType?->value.': '.$outcome->errorMessage),
        ]);

        MonitorWentDown::dispatch($monitor, $incident);
    }

    private function resolveOutage(Monitor $monitor): void
    {
        $incident = $this->openIncident($monitor, IncidentKind::Outage);

        if ($incident === null) {
            return;
        }

        $incident->update(['resolved_at' => $this->streakStartedAt($monitor, $this->stateMachine->successesToUp($monitor))]);

        MonitorRecovered::dispatch($monitor, $incident);
    }

    private function openDegradation(Monitor $monitor, CheckOutcome $outcome): void
    {
        $incident = $monitor->incidents()->create([
            'kind' => IncidentKind::Degradation,
            'started_at' => $this->streakStartedAt($monitor, $this->stateMachine->degradedToConfirm()),
            'cause' => $this->limit($outcome->warningType?->value.': '.$outcome->warningMessage),
        ]);

        MonitorDegraded::dispatch($monitor, $incident);
    }

    private function resolveDegradation(Monitor $monitor): void
    {
        $incident = $this->openIncident($monitor, IncidentKind::Degradation);

        if ($incident === null) {
            return;
        }

        $incident->update(['resolved_at' => $this->streakStartedAt($monitor, $this->stateMachine->successesToUp($monitor))]);

        MonitorDegradationResolved::dispatch($monitor, $incident);
    }

    private function openIncident(Monitor $monitor, IncidentKind $kind): ?Incident
    {
        return $monitor->incidents()->where('kind', $kind)->whereNull('resolved_at')->first();
    }

    private function awaitsConfirmation(Monitor $monitor, CheckOutcome $outcome): bool
    {
        return $monitor->status !== $this->stateMachine->settledStatus($outcome);
    }

    private function scheduleConfirmationCheck(Monitor $monitor): void
    {
        $confirmationCheckAt = now()->startOfMinute()->addSeconds(max(self::MIN_CONFIRMATION_DELAY_SECONDS, $monitor->interval_minutes * 30));

        if ($monitor->next_check_at === null || $monitor->next_check_at->greaterThan($confirmationCheckAt)) {
            $monitor->next_check_at = $confirmationCheckAt;
        }
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
