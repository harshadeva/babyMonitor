<?php

namespace App\Http\Requests;

use App\Enums\TemperatureMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemperatureReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'measured_at' => ['sometimes', 'date'],
            'value_celsius' => ['sometimes', 'numeric', 'between:30,43'],
            'method' => ['sometimes', Rule::enum(TemperatureMethod::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
