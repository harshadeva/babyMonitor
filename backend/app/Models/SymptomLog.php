<?php

namespace App\Models;

use App\Enums\SymptomTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SymptomLog extends Model
{
    protected $fillable = [
        'baby_id',
        'created_by',
        'client_uuid',
        'occurred_at',
        'tag',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'tag' => SymptomTag::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function baby(): BelongsTo
    {
        return $this->belongsTo(Baby::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
