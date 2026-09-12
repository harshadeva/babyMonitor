<?php

namespace App\Http\Requests;

use App\Enums\SymptomTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSymptomLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'],
            'occurred_at' => ['required', 'date'],
            'tag' => ['required', Rule::enum(SymptomTag::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
