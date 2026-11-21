<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_number' => $this->shipment_number,
            'status' => $this->status->value,
            'customer' => new UserResource($this->whenLoaded('customer')),
            'carrier' => new CarrierResource($this->whenLoaded('carrier')),
            'origin' => new LocationResource($this->whenLoaded('origin')),
            'destination' => new LocationResource($this->whenLoaded('destination')),
            'cargo_type' => $this->cargo_type,
            'weight_kg' => $this->weight_kg,
            'volume_cbm' => $this->volume_cbm,
            'expected_departure_at' => $this->expected_departure_at?->toIso8601String(),
            'expected_arrival_at' => $this->expected_arrival_at?->toIso8601String(),
            'estimated_arrival_at' => $this->estimated_arrival_at?->toIso8601String(),
            'actual_arrival_at' => $this->actual_arrival_at?->toIso8601String(),
            'delay' => [
                'is_delayed' => $this->is_delayed,
                'delay_hours' => $this->delay_hours,
                'severity' => $this->delay_severity?->value,
            ],
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'description' => $i->description,
                'quantity' => $i->quantity,
                'weight_kg' => $i->weight_kg,
                'volume_cbm' => $i->volume_cbm,
                'hs_code' => $i->hs_code,
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
