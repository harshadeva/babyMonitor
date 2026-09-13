<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSleepSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'started_at' => ['sometimes', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
