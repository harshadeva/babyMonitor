<?php

namespace App\Http\Requests;

use App\Enums\BottleContents;
use App\Enums\FeedingSide;
use App\Enums\FeedingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeedingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(FeedingType::class)],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'side' => ['nullable', Rule::enum(FeedingSide::class), 'required_if:type,breast'],
            'volume_ml' => ['nullable', 'integer', 'min:0', 'max:1000', 'required_if:type,bottle'],
            'contents' => ['nullable', Rule::enum(BottleContents::class), 'required_if:type,bottle'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
