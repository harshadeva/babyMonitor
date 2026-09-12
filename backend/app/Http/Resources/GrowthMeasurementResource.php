<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrowthMeasurementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'baby_id' => $this->baby_id,
            'client_uuid' => $this->client_uuid,
            'measured_at' => $this->measured_at?->toIso8601String(),
            'weight_grams' => $this->weight_grams,
            'length_cm' => $this->length_cm !== null ? (float) $this->length_cm : null,
            'head_circumference_cm' => $this->head_circumference_cm !== null ? (float) $this->head_circumference_cm : null,
            'notes' => $this->notes,
        ];
    }
}
