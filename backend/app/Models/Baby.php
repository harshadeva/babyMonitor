<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Baby extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'birth_date',
        'sex',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Caregivers who can view/log for this baby (e.g. both parents).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function feedingSessions(): HasMany
    {
        return $this->hasMany(FeedingSession::class);
    }

    public function sleepSessions(): HasMany
    {
        return $this->hasMany(SleepSession::class);
    }

    public function diaperChanges(): HasMany
    {
        return $this->hasMany(DiaperChange::class);
    }

    public function temperatureReadings(): HasMany
    {
        return $this->hasMany(TemperatureReading::class);
    }

    public function growthMeasurements(): HasMany
    {
        return $this->hasMany(GrowthMeasurement::class);
    }

    public function medicationDoses(): HasMany
    {
        return $this->hasMany(MedicationDose::class);
    }

    public function symptomLogs(): HasMany
    {
        return $this->hasMany(SymptomLog::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }
}
