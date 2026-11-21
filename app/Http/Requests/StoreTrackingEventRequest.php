<?php

namespace App\Http\Requests;

use App\Enums\ShipmentStatus;
use App\Enums\TrackingEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrackingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ShipmentPolicy::addTracking in the controller
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', Rule::in(TrackingEventType::inputValues())],
            'status' => ['required_if:event_type,'.TrackingEventType::StatusUpdate->value, 'nullable', Rule::in(ShipmentStatus::values())],
            'occurred_at' => ['required', 'date'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'description' => ['nullable', 'string', 'max:2000'],
            'estimated_arrival_at' => ['nullable', 'date'],
        ];
    }
}
