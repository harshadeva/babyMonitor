<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreMedicationDoseRequest;
use App\Http\Resources\MedicationDoseResource;
use App\Models\Baby;
use App\Models\MedicationDose;
use Illuminate\Http\Request;

class MedicationDoseController extends BabyScopedApiController
{
    public function index(Request $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        return MedicationDoseResource::collection(
            $baby->medicationDoses()->with('creator')->orderByDesc('given_at')->paginate(50)
        );
    }

    public function store(StoreMedicationDoseRequest $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $dose = $baby->medicationDoses()->updateOrCreate(
            ['client_uuid' => $data['client_uuid']],
            $data
        );
        $dose->setRelation('creator', $request->user());

        return new MedicationDoseResource($dose);
    }

    public function update(Request $request, MedicationDose $medication)
    {
        $this->ensureOwnsRecord($request, $medication);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'dose' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $medication->update($data);

        return new MedicationDoseResource($medication);
    }

    public function destroy(Request $request, MedicationDose $medication)
    {
        $this->ensureOwnsRecord($request, $medication);

        $medication->delete();

        return response()->noContent();
    }
}
