<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicationDoseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'baby_id' => $this->baby_id,
            'client_uuid' => $this->client_uuid,
            'given_at' => $this->given_at?->toIso8601String(),
            'name' => $this->name,
            'dose' => $this->dose,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
        ];
    }
}
