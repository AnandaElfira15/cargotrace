<?php

namespace App\Http\Requests;

use App\Enums\ExceptionType;
use App\Enums\Severity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ShipmentPolicy::reportException in the controller
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(ExceptionType::values())],
            'severity' => ['required', Rule::in(Severity::values())],
            'description' => ['nullable', 'string', 'max:2000'],
            'detected_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
