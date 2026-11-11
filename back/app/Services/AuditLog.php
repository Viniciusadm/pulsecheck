<?php

namespace App\Services;

use App\Models\AuditLogEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLog
{
    private const LABEL_ATTRIBUTES = ['name', 'title', 'email', 'slug'];

    public function __construct(private readonly Request $request) {}

    public function record(string $action, ?Model $subject = null, array $metadata = [], ?User $actor = null): AuditLogEntry
    {
        $actor ??= Auth::user();

        return AuditLogEntry::query()->create([
            'user_id' => $actor?->exists ? $actor->getKey() : null,
            'actor_name' => $actor?->name,
            'action' => $action,
            'subject_type' => $subject ? self::subjectType($subject) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subject ? $this->labelFor($subject) : null,
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => app()->runningInConsole() && ! app()->runningUnitTests() ? null : $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 250, '') ?: null,
        ]);
    }

    public static function subjectType(Model $subject): string
    {
        return Str::snake(class_basename($subject));
    }

    private function labelFor(Model $subject): ?string
    {
        foreach (self::LABEL_ATTRIBUTES as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return Str::limit($value, 250, '');
            }
        }

        return null;
    }
}
