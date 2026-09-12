<?php

namespace App\Http\Requests;

use App\Enums\TemperatureMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemperatureReadingRequest extends FormRequest
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
            'value_celsius' => ['required', 'numeric', 'between:30,43'],
            'method' => ['required', Rule::enum(TemperatureMethod::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
