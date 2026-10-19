<?php

namespace App\Http\Controllers;

use App\Enums\IncidentKind;
use App\Enums\MessageFormat;
use App\Enums\NotificationType;
use App\Models\Incident;
use App\Models\InstanceSettings;
use App\Models\Monitor;
use App\Notifications\AlertMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;

class NotificationChannelPreviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event' => ['required', Rule::enum(NotificationType::class)],
            'message_format' => ['required', Rule::enum(MessageFormat::class)],
        ]);

        App::setLocale(InstanceSettings::currentLocale());

        $type = NotificationType::from($validated['event']);
        $message = AlertMessage::fromIncident($this->sampleIncident($type), $type)->formattedAs(MessageFormat::from($validated['message_format']));

        return response()->json(['data' => [
            'event' => $message->event,
            'severity' => $message->severity->value,
            'title' => $message->title,
            'summary' => $message->summary,
            'fields' => array_map(
                fn (string $label, string $value) => ['label' => $label, 'value' => $value],
                array_keys($message->fields),
                $message->fields,
            ),
        ]]);
    }

    private function sampleIncident(NotificationType $type): Incident
    {
        $isDegradation = in_array($type, [NotificationType::Degraded, NotificationType::DegradationResolved], true);
        $isResolved = in_array($type, [NotificationType::Recovered, NotificationType::Closed, NotificationType::DegradationResolved], true);
        $startedAt = now()->subMinutes(42)->startOfMinute();

        $incident = new Incident([
            'kind' => $isDegradation ? IncidentKind::Degradation : IncidentKind::Outage,
            'started_at' => $startedAt,
            'resolved_at' => $isResolved ? $startedAt->copy()->addMinutes(37)->addSeconds(12) : null,
            'cause' => $isDegradation ? __('Response time :actual ms exceeds :threshold ms.', ['actual' => 2500, 'threshold' => 1000]) : 'HTTP 503',
        ]);
        $incident->reminder_count = 2;

        return $incident->setRelation('monitor', new Monitor(['name' => __('Example API'), 'url' => 'https://api.example.com/health']));
    }
}
