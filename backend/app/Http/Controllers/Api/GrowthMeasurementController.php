<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreGrowthMeasurementRequest;
use App\Http\Requests\UpdateGrowthMeasurementRequest;
use App\Http\Resources\GrowthMeasurementResource;
use App\Models\Baby;
use App\Models\GrowthMeasurement;
use Illuminate\Http\Request;

class GrowthMeasurementController extends BabyScopedApiController
{
    public function index(Request $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $query = $baby->growthMeasurements()->with('creator')->orderByDesc('measured_at');

        if ($request->filled('from')) {
            $query->where('measured_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('measured_at', '<=', $request->date('to'));
        }

        return GrowthMeasurementResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(StoreGrowthMeasurementRequest $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $measurement = $baby->growthMeasurements()->updateOrCreate(
            ['client_uuid' => $data['client_uuid']],
            $data
        );
        $measurement->setRelation('creator', $request->user());

        return new GrowthMeasurementResource($measurement);
    }

    public function update(UpdateGrowthMeasurementRequest $request, GrowthMeasurement $growth)
    {
        $this->ensureOwnsRecord($request, $growth);

        $data = $request->validated();

        $growth->update($data);

        return new GrowthMeasurementResource($growth->load('creator'));
    }

    public function destroy(Request $request, GrowthMeasurement $growth)
    {
        $this->ensureOwnsRecord($request, $growth);

        $growth->delete();

        return response()->noContent();
    }
}
