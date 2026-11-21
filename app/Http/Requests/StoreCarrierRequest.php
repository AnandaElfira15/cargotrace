<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // protected by role:admin middleware
    }

    protected function prepareForValidation(): void
    {
        // HTML checkbox sends nothing when unchecked
        if (! $this->is('api/*')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }

    public function rules(): array
    {
        $carrier = $this->route('carrier');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('carriers', 'code')->ignore($carrier?->id)],
            'mode' => ['required', Rule::in(['sea', 'air', 'road', 'rail'])],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
