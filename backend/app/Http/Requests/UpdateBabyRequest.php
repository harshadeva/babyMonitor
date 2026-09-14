<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBabyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'birth_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'sex' => ['nullable', 'in:male,female,unspecified'],
        ];
    }
}
