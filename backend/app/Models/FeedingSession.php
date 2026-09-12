<?php

namespace App\Models;

use App\Enums\BottleContents;
use App\Enums\FeedingSide;
use App\Enums\FeedingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedingSession extends Model
{
    protected $fillable = [
        'baby_id',
        'client_uuid',
        'type',
        'started_at',
        'ended_at',
        'side',
        'volume_ml',
        'contents',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => FeedingType::class,
            'side' => FeedingSide::class,
            'contents' => BottleContents::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function baby(): BelongsTo
    {
        return $this->belongsTo(Baby::class);
    }
}
