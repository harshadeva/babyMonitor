<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationDose extends Model
{
    protected $fillable = [
        'baby_id',
        'created_by',
        'client_uuid',
        'given_at',
        'name',
        'dose',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'given_at' => 'datetime',
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
