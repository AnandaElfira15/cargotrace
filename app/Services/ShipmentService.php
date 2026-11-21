<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Enums\TrackingEventType;
use App\Models\Shipment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ShipmentService
{
    public function __construct(
        private readonly EtaService $eta,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Create a shipment in CREATED status together with its items and the first
     * (historical) tracking event. $createdAt exists so seeders can back-date data.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor, ?CarbonInterface $createdAt = null): Shipment
    {
        return DB::transaction(function () use ($data, $actor, $createdAt) {
            $items = Arr::pull($data, 'items', []);
            $createdAt ??= now();

            $shipment = new Shipment($data);
            $shipment->shipment_number = $data['shipment_number'] ?? $this->generateNumber($createdAt);
            $shipment->status = ShipmentStatus::Created;
            $shipment->created_by = $actor->id;
            $shipment->save();

            foreach ($items as $item) {
                $shipment->items()->create($item);
            }

            $shipment->load(['origin', 'customer']);

            $shipment->trackingEvents()->create([
                'event_type' => TrackingEventType::StatusUpdate,
                'status' => ShipmentStatus::Created,
                'occurred_at' => $createdAt,
                'location_id' => $shipment->origin_location_id,
                'latitude' => $shipment->origin->latitude,
                'longitude' => $shipment->origin->longitude,
                'description' => 'Shipment created.',
                'is_exception' => false,
                'created_by' => $actor->id,
            ]);

            $this->eta->evaluate($shipment);

            $this->audit->log('shipment.created', $shipment, null, [
                'shipment_number' => $shipment->shipment_number,
                'customer_id' => $shipment->customer_id,
                'carrier_id' => $shipment->carrier_id,
            ], $actor);

            $this->notifications->notify(
                [$shipment->customer],
                'shipment.created',
                "Shipment {$shipment->shipment_number} created",
                'A new shipment has been registered for you.',
                $shipment
            );

            return $shipment->load(['customer', 'carrier', 'origin', 'destination', 'items']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Shipment $shipment, array $data, User $actor): Shipment
    {
        return DB::transaction(function () use ($shipment, $data, $actor) {
            $hasItems = array_key_exists('items', $data);
            $items = Arr::pull($data, 'items', []);

            $shipment->fill($data);
            $dirty = $shipment->getDirty();
            $old = array_intersect_key($shipment->getOriginal(), $dirty);
            $shipment->save();

            if ($hasItems) {
                $shipment->items()->delete();
                foreach ($items as $item) {
                    $shipment->items()->create($item);
                }
            }

            // Expected/estimated arrival may have changed, so re-run the delay rules.
            $this->eta->evaluate($shipment);

            if ($dirty || $hasItems) {
                $this->audit->log(
                    'shipment.updated',
                    $shipment,
                    $this->stringify($old),
                    $this->stringify($dirty) + ($hasItems ? ['items' => 'replaced'] : []),
                    $actor
                );
            }

            return $shipment->load(['customer', 'carrier', 'origin', 'destination', 'items']);
        });
    }

    public function delete(Shipment $shipment, User $actor): void
    {
        DB::transaction(function () use ($shipment, $actor) {
            $this->audit->log('shipment.deleted', $shipment, ['shipment_number' => $shipment->shipment_number], null, $actor);
            $shipment->delete(); // soft delete: history stays recoverable
        });
    }

    public function generateNumber(CarbonInterface $at): string
    {
        do {
            $number = 'CT'.$at->format('ymd').'-'.str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (Shipment::withTrashed()->where('shipment_number', $number)->exists());

        return $number;
    }

    /** @param array<string, mixed> $values */
    private function stringify(array $values): array
    {
        return array_map(
            fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : (is_scalar($v) || $v === null ? $v : (string) $v),
            $values
        );
    }
}
