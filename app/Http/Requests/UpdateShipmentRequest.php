<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization is done by ShipmentPolicy in the controller
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('items') && is_array($this->input('items'))) {
            $items = collect($this->input('items'))
                ->filter(fn ($i) => is_array($i) && filled($i['description'] ?? null))
                ->values()
                ->all();
            $this->merge(['items' => $items]);
        }
    }

    public function rules(): array
    {
        $shipment = $this->route('shipment');

        return [
            'shipment_number' => ['sometimes', 'string', 'max:30', Rule::unique('shipments', 'shipment_number')->ignore($shipment?->id)],
            'customer_id' => ['sometimes', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Customer->value)],
            'carrier_id' => ['sometimes', 'integer', 'exists:carriers,id'],
            'origin_location_id' => ['sometimes', 'integer', 'exists:locations,id'],
            'destination_location_id' => ['sometimes', 'integer', 'exists:locations,id'],
            'cargo_type' => ['sometimes', 'string', 'max:100'],
            'weight_kg' => ['sometimes', 'numeric', 'min:0.01', 'max:100000000'],
            'volume_cbm' => ['sometimes', 'numeric', 'min:0.01', 'max:100000000'],
            'expected_departure_at' => ['sometimes', 'date'],
            'expected_arrival_at' => ['sometimes', 'date'],
            'estimated_arrival_at' => ['sometimes', 'nullable', 'date'],
            'items' => ['sometimes', 'nullable', 'array', 'max:50'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'items.*.volume_cbm' => ['nullable', 'numeric', 'min:0'],
            'items.*.hs_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** Cross-field checks use the stored value when only one of the fields is sent (PATCH). */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $shipment = $this->route('shipment');
                if (! $shipment || $validator->errors()->hasAny(['expected_departure_at', 'expected_arrival_at'])) {
                    return;
                }

                $departure = $this->date('expected_departure_at') ?? $shipment->expected_departure_at;
                $arrival = $this->date('expected_arrival_at') ?? $shipment->expected_arrival_at;
                if ($arrival->lessThanOrEqualTo($departure)) {
                    $validator->errors()->add('expected_arrival_at', 'The expected arrival must be after the expected departure.');
                }

                $origin = $this->input('origin_location_id', $shipment->origin_location_id);
                $destination = $this->input('destination_location_id', $shipment->destination_location_id);
                if ((int) $origin === (int) $destination) {
                    $validator->errors()->add('destination_location_id', 'Origin and destination must be different.');
                }
            },
        ];
    }
}
