<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Enums\TrackingEventType;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrackingService
{
    public function __construct(
        private readonly EtaService $eta,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Append a tracking event. History is append-only: old events are never modified.
     * A STATUS_UPDATE event is the only way a shipment status can change, and every
     * change is validated against ShipmentStatus::allowedTransitions().
     *
     * @param array{event_type: string, status?: ?string, occurred_at: string, location_id?: ?int,
     *              latitude?: ?float, longitude?: ?float, description?: ?string, estimated_arrival_at?: ?string} $data
     */
    public function record(Shipment $shipment, array $data, User $actor): TrackingEvent
    {
        return DB::transaction(function () use ($shipment, $data, $actor) {
            /** @var Shipment $shipment */
            $shipment = Shipment::query()->whereKey($shipment->getKey())->lockForUpdate()->firstOrFail();

            if ($shipment->status->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => ["Shipment is already {$shipment->status->value}; no further events can be added."],
                ]);
            }

            $type = TrackingEventType::from($data['event_type']);
            $occurredAt = Carbon::parse($data['occurred_at']);

            $tolerance = (int) config('cargotrace.tracking.future_tolerance_minutes', 5);
            if ($occurredAt->greaterThan(now()->addMinutes($tolerance))) {
                throw ValidationException::withMessages(['occurred_at' => ['The event time cannot be in the future.']]);
            }

            $latest = $shipment->trackingEvents()->orderByDesc('occurred_at')->orderByDesc('id')->first();
            if ($latest && $occurredAt->lessThan($latest->occurred_at)) {
                throw ValidationException::withMessages([
                    'occurred_at' => ['The event time cannot be earlier than the latest recorded event ('.$latest->occurred_at->format('Y-m-d H:i').').'],
                ]);
            }

            $newStatus = null;
            if ($type === TrackingEventType::StatusUpdate) {
                $newStatus = ShipmentStatus::tryFrom((string) ($data['status'] ?? ''));
                if (! $newStatus) {
                    throw ValidationException::withMessages(['status' => ['A valid status is required for a STATUS_UPDATE event.']]);
                }
                if (! $shipment->status->canTransitionTo($newStatus)) {
                    $allowed = implode(', ', array_map(fn ($s) => $s->value, $shipment->status->allowedTransitions()));
                    throw ValidationException::withMessages([
                        'status' => ["Invalid status transition {$shipment->status->value} -> {$newStatus->value}. Allowed: {$allowed}."],
                    ]);
                }
            }

            $location = ! empty($data['location_id']) ? Location::query()->find($data['location_id']) : null;
            $latitude = $data['latitude'] ?? $location?->latitude;
            $longitude = $data['longitude'] ?? $location?->longitude;

            $event = $shipment->trackingEvents()->create([
                'event_type' => $type,
                'status' => $newStatus,
                'occurred_at' => $occurredAt,
                'location_id' => $location?->id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'description' => $data['description'] ?? null,
                'is_exception' => false,
                'created_by' => $actor->id,
            ]);

            $previousStatus = $shipment->status;
            if ($newStatus) {
                $shipment->status = $newStatus;
                if ($newStatus === ShipmentStatus::Delivered) {
                    $shipment->actual_arrival_at = $occurredAt;
                }
            }
            if (! empty($data['estimated_arrival_at'])) {
                $shipment->estimated_arrival_at = Carbon::parse($data['estimated_arrival_at']);
            }
            $shipment->save();

            $this->eta->evaluate($shipment);

            $this->audit->log(
                'tracking.added',
                $event,
                null,
                ['shipment_id' => $shipment->id, 'event_type' => $type->value, 'status' => $newStatus?->value, 'occurred_at' => $occurredAt->toDateTimeString()],
                $actor
            );

            if ($newStatus) {
                $this->audit->log('shipment.status_changed', $shipment, ['status' => $previousStatus->value], ['status' => $newStatus->value], $actor);
                $this->notifications->notifyStakeholders(
                    $shipment,
                    'shipment.status',
                    "Shipment {$shipment->shipment_number} is now {$newStatus->value}",
                    "Status changed from {$previousStatus->value} to {$newStatus->value}."
                );
            }

            return $event->load(['location', 'creator']);
        });
    }

    /** Chronological timeline (oldest first). */
    public function timeline(Shipment $shipment): Collection
    {
        return $shipment->trackingEvents()
            ->with(['location', 'creator'])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }

    /** Current location = newest event that carries a location or coordinates. */
    public function currentLocation(Shipment $shipment): ?TrackingEvent
    {
        return $shipment->trackingEvents()
            ->with('location')
            ->where(function ($q) {
                $q->whereNotNull('latitude')->orWhereNotNull('location_id');
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();
    }
}
