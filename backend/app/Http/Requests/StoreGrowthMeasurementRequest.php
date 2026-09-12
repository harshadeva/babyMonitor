<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGrowthMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'],
            'measured_at' => ['required', 'date'],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:30000'],
            'length_cm' => ['nullable', 'numeric', 'between:0,120'],
            'head_circumference_cm' => ['nullable', 'numeric', 'between:0,60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
