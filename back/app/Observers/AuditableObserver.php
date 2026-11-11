<?php

namespace App\Observers;

use App\Services\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditableObserver
{
    private const IGNORED_ATTRIBUTES = [
        'id',
        'created_at',
        'updated_at',
        'password',
        'remember_token',
        'dashboard_layout',
        'dashboard_period',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'two_factor_last_used_step',
        'status',
        'consecutive_failures',
        'consecutive_successes',
        'consecutive_degraded',
        'consecutive_healthy',
        'last_status_code',
        'last_latency_ms',
        'last_checked_at',
        'last_failed_at',
        'next_check_at',
        'config_revision',
        'last_heartbeat_at',
        'certificate_expires_at',
        'maintenance_ends_at',
    ];

    public function __construct(private readonly AuditLog $auditLog) {}

    public function created(Model $model): void
    {
        $this->recordIfUserAction($model, 'created');
    }

    public function updated(Model $model): void
    {
        $fields = array_values(array_diff(array_keys($model->getChanges()), self::IGNORED_ATTRIBUTES));

        if ($fields !== []) {
            $this->recordIfUserAction($model, 'updated', ['fields' => $fields]);
        }
    }

    public function deleted(Model $model): void
    {
        $this->recordIfUserAction($model, 'deleted');
    }

    private function recordIfUserAction(Model $model, string $event, array $metadata = []): void
    {
        if (Auth::hasUser()) {
            $this->auditLog->record(AuditLog::subjectType($model).'.'.$event, $model, $metadata);
        }
    }
}
