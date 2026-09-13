<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreTemperatureReadingRequest;
use App\Http\Requests\UpdateTemperatureReadingRequest;
use App\Http\Resources\TemperatureReadingResource;
use App\Models\Baby;
use App\Models\TemperatureReading;
use Illuminate\Http\Request;

class TemperatureReadingController extends BabyScopedApiController
{
    /** A rectal/oral temperature at or above this is a fever worth flagging. */
    private const FEVER_THRESHOLD_CELSIUS = 38.0;

    public function index(Request $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $query = $baby->temperatureReadings()->with('creator')->orderByDesc('measured_at');

        if ($request->filled('from')) {
            $query->where('measured_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('measured_at', '<=', $request->date('to'));
        }

        return TemperatureReadingResource::collection($query->paginate(50));
    }

    public function store(StoreTemperatureReadingRequest $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $duplicate = $this->findPossibleDuplicate(
            $baby->temperatureReadings()->where('client_uuid', '!=', $data['client_uuid']),
            'measured_at',
            new \DateTimeImmutable($data['measured_at'])
        );

        $reading = $baby->temperatureReadings()->updateOrCreate(
            ['client_uuid' => $data['client_uuid']],
            $data
        );
        $reading->setRelation('creator', $request->user());

        if ($duplicate) {
            $reading->possible_duplicate_of = $duplicate->id;
        }

        return (new TemperatureReadingResource($reading))->additional([
            'meta' => ['is_fever' => $reading->value_celsius >= self::FEVER_THRESHOLD_CELSIUS],
        ]);
    }

    public function update(UpdateTemperatureReadingRequest $request, TemperatureReading $temperature)
    {
        $this->ensureOwnsRecord($request, $temperature);

        $data = $request->validated();

        $temperature->update($data);

        return (new TemperatureReadingResource($temperature->load('creator')))->additional([
            'meta' => ['is_fever' => $temperature->value_celsius >= self::FEVER_THRESHOLD_CELSIUS],
        ]);
    }

    public function destroy(Request $request, TemperatureReading $temperature)
    {
        $this->ensureOwnsRecord($request, $temperature);

        $temperature->delete();

        return response()->noContent();
    }
}
