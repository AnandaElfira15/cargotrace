<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization is done by ShipmentPolicy in the controller
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('items') && is_array($this->input('items'))) {
            // Drop empty repeater rows coming from the HTML form
            $items = collect($this->input('items'))
                ->filter(fn ($i) => is_array($i) && filled($i['description'] ?? null))
                ->values()
                ->all();
            $this->merge(['items' => $items]);
        }
    }

    public function rules(): array
    {
        return [
            'shipment_number' => ['nullable', 'string', 'max:30', 'unique:shipments,shipment_number'],
            'customer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Customer->value)],
            'carrier_id' => ['required', 'integer', Rule::exists('carriers', 'id')->where('is_active', true)],
            'origin_location_id' => ['required', 'integer', 'exists:locations,id'],
            'destination_location_id' => ['required', 'integer', 'exists:locations,id', 'different:origin_location_id'],
            'cargo_type' => ['required', 'string', 'max:100'],
            'weight_kg' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'volume_cbm' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'expected_departure_at' => ['required', 'date'],
            'expected_arrival_at' => ['required', 'date', 'after:expected_departure_at'],
            'estimated_arrival_at' => ['nullable', 'date'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'items.*.volume_cbm' => ['nullable', 'numeric', 'min:0'],
            'items.*.hs_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
