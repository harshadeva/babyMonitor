<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemperatureReadingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'baby_id' => $this->baby_id,
            'client_uuid' => $this->client_uuid,
            'measured_at' => $this->measured_at?->toIso8601String(),
            'value_celsius' => (float) $this->value_celsius,
            'method' => $this->method->value,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'possible_duplicate_of' => $this->when(isset($this->possible_duplicate_of), $this->possible_duplicate_of),
        ];
    }
}
