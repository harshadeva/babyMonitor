<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreSleepSessionRequest;
use App\Http\Resources\SleepSessionResource;
use App\Models\Baby;
use App\Models\SleepSession;
use Illuminate\Http\Request;

class SleepSessionController extends BabyScopedApiController
{
    public function index(Request $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $query = $baby->sleepSessions()->orderByDesc('started_at');

        if ($request->filled('from')) {
            $query->where('started_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('started_at', '<=', $request->date('to'));
        }

        return SleepSessionResource::collection($query->paginate(50));
    }

    public function store(StoreSleepSessionRequest $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $data = $request->validated();

        $duplicate = $this->findPossibleDuplicate(
            $baby->sleepSessions()->where('client_uuid', '!=', $data['client_uuid']),
            'started_at',
            new \DateTimeImmutable($data['started_at'])
        );

        $sleep = $baby->sleepSessions()->updateOrCreate(
            ['client_uuid' => $data['client_uuid']],
            $data
        );

        if ($duplicate) {
            $sleep->possible_duplicate_of = $duplicate->id;
        }

        return new SleepSessionResource($sleep);
    }

    public function update(Request $request, SleepSession $sleep)
    {
        $this->ensureOwnsRecord($request, $sleep);

        $data = $request->validate([
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $sleep->update($data);

        return new SleepSessionResource($sleep);
    }

    public function destroy(Request $request, SleepSession $sleep)
    {
        $this->ensureOwnsRecord($request, $sleep);

        $sleep->delete();

        return response()->noContent();
    }
}
