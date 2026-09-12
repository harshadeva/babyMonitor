<?php

namespace App\Models;

use App\Enums\TemperatureMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemperatureReading extends Model
{
    protected $fillable = [
        'baby_id',
        'client_uuid',
        'measured_at',
        'value_celsius',
        'method',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'method' => TemperatureMethod::class,
            'measured_at' => 'datetime',
            'value_celsius' => 'decimal:1',
        ];
    }

    public function baby(): BelongsTo
    {
        return $this->belongsTo(Baby::class);
    }
}
