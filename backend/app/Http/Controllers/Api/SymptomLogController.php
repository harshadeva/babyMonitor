<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreSymptomLogRequest;
use App\Http\Resources\SymptomLogResource;
use App\Models\Baby;
use App\Models\SymptomLog;
use Illuminate\Http\Request;

class SymptomLogController extends BabyScopedApiController
{
    public function index(Request $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        return SymptomLogResource::collection(
            $baby->symptomLogs()->with('creator')->orderByDesc('occurred_at')->paginate(50)
        );
    }

    public function store(StoreSymptomLogRequest $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $log = $baby->symptomLogs()->updateOrCreate(
            ['client_uuid' => $data['client_uuid']],
            $data
        );
        $log->setRelation('creator', $request->user());

        return new SymptomLogResource($log);
    }

    public function update(Request $request, SymptomLog $symptom)
    {
        $this->ensureOwnsRecord($request, $symptom);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $symptom->update($data);

        return new SymptomLogResource($symptom);
    }

    public function destroy(Request $request, SymptomLog $symptom)
    {
        $this->ensureOwnsRecord($request, $symptom);

        $symptom->delete();

        return response()->noContent();
    }
}
