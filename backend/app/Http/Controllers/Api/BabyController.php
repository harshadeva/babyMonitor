<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreBabyRequest;
use App\Http\Requests\UpdateBabyRequest;
use App\Http\Resources\BabyResource;
use App\Http\Resources\DiaperChangeResource;
use App\Http\Resources\FeedingSessionResource;
use App\Http\Resources\GrowthMeasurementResource;
use App\Http\Resources\MedicationDoseResource;
use App\Http\Resources\MilestoneResource;
use App\Http\Resources\SleepSessionResource;
use App\Http\Resources\SymptomLogResource;
use App\Http\Resources\TemperatureReadingResource;
use App\Models\Baby;
use Illuminate\Http\Request;

class BabyController extends BabyScopedApiController
{
    public function index(Request $request)
    {
        return BabyResource::collection(
            $request->user()->babies()->orderBy('birth_date')->get()
        );
    }

    public function store(StoreBabyRequest $request)
    {
        $baby = Baby::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);
        $baby->users()->attach($request->user()->id);

        return new BabyResource($baby);
    }

    public function show(Request $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        return new BabyResource($baby);
    }

    public function update(UpdateBabyRequest $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $baby->update($request->validated());

        return new BabyResource($baby);
    }

    /**
     * A full, unpaginated dump of every activity record for this baby —
     * for the user's own "export my data" download, not for the app itself.
     */
    public function export(Request $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'baby' => new BabyResource($baby),
            'data' => [
                'feedings' => FeedingSessionResource::collection(
                    $baby->feedingSessions()->with('creator')->orderBy('started_at')->get()
                ),
                'sleeps' => SleepSessionResource::collection(
                    $baby->sleepSessions()->with('creator')->orderBy('started_at')->get()
                ),
                'diapers' => DiaperChangeResource::collection(
                    $baby->diaperChanges()->with('creator')->orderBy('occurred_at')->get()
                ),
                'temperatures' => TemperatureReadingResource::collection(
                    $baby->temperatureReadings()->with('creator')->orderBy('measured_at')->get()
                ),
                'growths' => GrowthMeasurementResource::collection(
                    $baby->growthMeasurements()->with('creator')->orderBy('measured_at')->get()
                ),
                'medications' => MedicationDoseResource::collection(
                    $baby->medicationDoses()->with('creator')->orderBy('given_at')->get()
                ),
                'symptoms' => SymptomLogResource::collection(
                    $baby->symptomLogs()->with('creator')->orderBy('occurred_at')->get()
                ),
                'milestones' => MilestoneResource::collection(
                    $baby->milestones()->with('creator')->orderBy('occurred_at')->get()
                ),
            ],
        ]);
    }
}
