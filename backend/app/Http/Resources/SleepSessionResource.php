<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SleepSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'baby_id' => $this->baby_id,
            'client_uuid' => $this->client_uuid,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'notes' => $this->notes,
            'possible_duplicate_of' => $this->when(isset($this->possible_duplicate_of), $this->possible_duplicate_of),
        ];
    }
}
