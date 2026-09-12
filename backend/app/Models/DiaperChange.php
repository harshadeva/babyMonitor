<?php

namespace App\Models;

use App\Enums\DiaperProduct;
use App\Enums\StoolConsistency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaperChange extends Model
{
    protected $fillable = [
        'baby_id',
        'client_uuid',
        'occurred_at',
        'product',
        'wet',
        'dirty',
        'stool_color_name',
        'stool_color_hex',
        'stool_consistency',
        'flagged_for_doctor',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'product' => DiaperProduct::class,
            'stool_consistency' => StoolConsistency::class,
            'occurred_at' => 'datetime',
            'wet' => 'boolean',
            'dirty' => 'boolean',
            'flagged_for_doctor' => 'boolean',
        ];
    }

    public function baby(): BelongsTo
    {
        return $this->belongsTo(Baby::class);
    }
}
