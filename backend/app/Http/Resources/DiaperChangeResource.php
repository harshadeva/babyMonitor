<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiaperChangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'baby_id' => $this->baby_id,
            'client_uuid' => $this->client_uuid,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'product' => $this->product->value,
            'wet' => $this->wet,
            'dirty' => $this->dirty,
            'stool_color_name' => $this->stool_color_name,
            'stool_color_hex' => $this->stool_color_hex,
            'stool_consistency' => $this->stool_consistency?->value,
            'flagged_for_doctor' => $this->flagged_for_doctor,
            'notes' => $this->notes,
            'possible_duplicate_of' => $this->when(isset($this->possible_duplicate_of), $this->possible_duplicate_of),
        ];
    }
}
