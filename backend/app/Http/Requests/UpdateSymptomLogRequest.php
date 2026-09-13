<?php

namespace App\Http\Requests;

use App\Enums\SymptomTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSymptomLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'occurred_at' => ['sometimes', 'date'],
            'tag' => ['sometimes', Rule::enum(SymptomTag::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
