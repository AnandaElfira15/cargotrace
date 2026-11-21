<?php

namespace App\Services;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\Severity;
use App\Enums\TrackingEventType;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExceptionService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    /** @param array{type: string, severity: string, description?: ?string, detected_at?: ?string} $data */
    public function open(Shipment $shipment, array $data, User $actor): ShipmentException
    {
        return DB::transaction(function () use ($shipment, $data, $actor) {
            $detectedAt = ! empty($data['detected_at']) ? Carbon::parse($data['detected_at']) : now();

            $exception = new ShipmentException([
                'type' => $data['type'],
                'severity' => $data['severity'],
                'description' => $data['description'] ?? null,
                'detected_at' => $detectedAt,
            ]);
            $exception->shipment_id = $shipment->id;
            $exception->status = ExceptionStatus::Open;
            $exception->reported_by = $actor->id;
            $exception->save();

            // Mirror it in the timeline, keeping the timeline chronological.
            $latest = $shipment->trackingEvents()->max('occurred_at');
            $eventTime = $latest && Carbon::parse($latest)->greaterThan($detectedAt) ? Carbon::parse($latest) : $detectedAt;
            $type = ExceptionType::from($data['type']);
            $lastLocation = $shipment->trackingEvents()->whereNotNull('latitude')->orderByDesc('occurred_at')->orderByDesc('id')->first();

            $shipment->trackingEvents()->create([
                'event_type' => TrackingEventType::Exception,
                'status' => null,
                'occurred_at' => $eventTime,
                'location_id' => $lastLocation?->location_id,
                'latitude' => $lastLocation?->latitude,
                'longitude' => $lastLocation?->longitude,
                'description' => "Exception {$type->value}: ".($data['description'] ?? 'no details'),
                'is_exception' => true,
                'created_by' => $actor->id,
            ]);

            $this->audit->log('exception.opened', $exception, null, [
                'shipment_id' => $shipment->id, 'type' => $type->value, 'severity' => $data['severity'],
            ], $actor);

            $this->notifications->notifyStakeholders(
                $shipment,
                'exception.opened',
                "Exception on shipment {$shipment->shipment_number}",
                "{$type->label()} ({$data['severity']}) was reported.".(! empty($data['description']) ? " {$data['description']}" : '')
            );

            return $exception->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function update(ShipmentException $exception, array $data, User $actor): ShipmentException
    {
        return DB::transaction(function () use ($exception, $data, $actor) {
            if ($exception->status->isTerminal()) {
                throw ValidationException::withMessages(['status' => ['A resolved exception can no longer be changed.']]);
            }

            $old = $exception->only(['type', 'severity', 'status', 'description', 'resolution_notes']);
            $old = array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $old);

            $next = isset($data['status']) ? ExceptionStatus::from($data['status']) : $exception->status;
            if ($next !== $exception->status && ! $exception->status->canTransitionTo($next)) {
                throw ValidationException::withMessages([
                    'status' => ["Invalid exception status transition {$exception->status->value} -> {$next->value}."],
                ]);
            }

            if (isset($data['severity'])) {
                $exception->severity = Severity::from($data['severity']);
            }
            if (array_key_exists('description', $data)) {
                $exception->description = $data['description'];
            }
            if (array_key_exists('resolution_notes', $data)) {
                $exception->resolution_notes = $data['resolution_notes'];
            }

            $exception->status = $next;

            if ($next === ExceptionStatus::Resolved) {
                $resolvedAt = ! empty($data['resolved_at']) ? Carbon::parse($data['resolved_at']) : now();
                if ($resolvedAt->lessThan($exception->detected_at)) {
                    throw ValidationException::withMessages(['resolved_at' => ['Resolution time cannot be before the detection time.']]);
                }
                $exception->resolved_at = $resolvedAt;
                $exception->resolved_by = $actor->id;
            }

            $exception->save();

            $new = $exception->only(['type', 'severity', 'status', 'description', 'resolution_notes']);
            $new = array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $new);
            $this->audit->log('exception.updated', $exception, $old, $new, $actor);

            if ($next === ExceptionStatus::Resolved) {
                $shipment = $exception->shipment;
                $this->notifications->notifyStakeholders(
                    $shipment,
                    'exception.resolved',
                    "Exception resolved on shipment {$shipment->shipment_number}",
                    "{$exception->type->label()} was marked as resolved."
                );
            }

            return $exception->refresh();
        });
    }
}
