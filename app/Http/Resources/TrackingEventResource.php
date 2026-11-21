<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'event_type' => $this->event_type->value,
            'status' => $this->status?->value,
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'location' => new LocationResource($this->whenLoaded('location')),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'description' => $this->description,
            'is_exception' => $this->is_exception,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null),
        ];
    }
}
