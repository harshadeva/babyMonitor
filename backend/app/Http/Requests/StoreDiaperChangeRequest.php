<?php

namespace App\Http\Requests;

use App\Enums\DiaperProduct;
use App\Enums\StoolConsistency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiaperChangeRequest extends FormRequest
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
            'product' => ['required', Rule::enum(DiaperProduct::class)],
            'wet' => ['required', 'boolean'],
            'dirty' => ['required', 'boolean'],
            'stool_color_name' => ['nullable', 'string', 'max:50', 'required_if:dirty,true'],
            'stool_color_hex' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'stool_consistency' => ['nullable', Rule::enum(StoolConsistency::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
