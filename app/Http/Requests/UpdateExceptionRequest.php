<?php

namespace App\Http\Requests;

use App\Enums\ExceptionStatus;
use App\Enums\Severity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ShipmentExceptionPolicy::update in the controller
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(ExceptionStatus::values())],
            'severity' => ['sometimes', Rule::in(Severity::values())],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'resolution_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'resolved_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
