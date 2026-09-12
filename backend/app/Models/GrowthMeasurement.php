<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthMeasurement extends Model
{
    protected $fillable = [
        'baby_id',
        'client_uuid',
        'measured_at',
        'weight_grams',
        'length_cm',
        'head_circumference_cm',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'length_cm' => 'decimal:1',
            'head_circumference_cm' => 'decimal:1',
        ];
    }

    public function baby(): BelongsTo
    {
        return $this->belongsTo(Baby::class);
    }
}
