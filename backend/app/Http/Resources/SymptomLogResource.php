<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SymptomLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'baby_id' => $this->baby_id,
            'client_uuid' => $this->client_uuid,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'tag' => $this->tag->value,
            'notes' => $this->notes,
        ];
    }
}
