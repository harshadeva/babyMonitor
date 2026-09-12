<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'baby_id' => $this->baby_id,
            'client_uuid' => $this->client_uuid,
            'type' => $this->type->value,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'side' => $this->side?->value,
            'volume_ml' => $this->volume_ml,
            'contents' => $this->contents?->value,
            'notes' => $this->notes,
            'possible_duplicate_of' => $this->when(isset($this->possible_duplicate_of), $this->possible_duplicate_of),
        ];
    }
}
