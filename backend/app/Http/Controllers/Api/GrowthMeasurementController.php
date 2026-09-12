<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreGrowthMeasurementRequest;
use App\Http\Resources\GrowthMeasurementResource;
use App\Models\Baby;
use App\Models\GrowthMeasurement;
use Illuminate\Http\Request;

class GrowthMeasurementController extends BabyScopedApiController
{
    public function index(Request $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        return GrowthMeasurementResource::collection(
            $baby->growthMeasurements()->orderByDesc('measured_at')->paginate(50)
        );
    }

    public function store(StoreGrowthMeasurementRequest $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $data = $request->validated();

        $measurement = $baby->growthMeasurements()->updateOrCreate(
            ['client_uuid' => $data['client_uuid']],
            $data
        );

        return new GrowthMeasurementResource($measurement);
    }

    public function update(Request $request, GrowthMeasurement $growth)
    {
        $this->ensureOwnsRecord($request, $growth);

        $data = $request->validate([
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:30000'],
            'length_cm' => ['nullable', 'numeric', 'between:0,120'],
            'head_circumference_cm' => ['nullable', 'numeric', 'between:0,60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $growth->update($data);

        return new GrowthMeasurementResource($growth);
    }

    public function destroy(Request $request, GrowthMeasurement $growth)
    {
        $this->ensureOwnsRecord($request, $growth);

        $growth->delete();

        return response()->noContent();
    }
}
