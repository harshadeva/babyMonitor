<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreSleepSessionRequest;
use App\Http\Requests\UpdateSleepSessionRequest;
use App\Http\Resources\SleepSessionResource;
use App\Models\Baby;
use App\Models\SleepSession;
use Illuminate\Http\Request;

class SleepSessionController extends BabyScopedApiController
{
    public function index(Request $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $query = $baby->sleepSessions()->with('creator')->orderByDesc('started_at');

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

        // Starting a sleep (no ended_at yet) is how caregivers signal "asleep
        // now" to each other. If one is already open, hand that back instead
        // of opening a second one — e.g. a caregiver's screen was stale and
        // didn't know the other had already started the timer.
        if (empty($data['ended_at'])) {
            $activeSession = $baby->sleepSessions()->whereNull('ended_at')->with('creator')->first();
            if ($activeSession) {
                return new SleepSessionResource($activeSession);
            }
        }

        $data['created_by'] = $request->user()->id;

        $duplicate = $this->findPossibleDuplicate(
            $baby->sleepSessions()->where('client_uuid', '!=', $data['client_uuid']),
            'started_at',
            new \DateTimeImmutable($data['started_at'])
        );

        $sleep = $baby->sleepSessions()->updateOrCreate(
            ['client_uuid' => $data['client_uuid']],
            $data
        );
        $sleep->setRelation('creator', $request->user());

        if ($duplicate) {
            $sleep->possible_duplicate_of = $duplicate->id;
        }

        return new SleepSessionResource($sleep);
    }

    public function update(UpdateSleepSessionRequest $request, SleepSession $sleep)
    {
        $this->ensureOwnsRecord($request, $sleep);

        $data = $request->validated();

        $sleep->update($data);

        return new SleepSessionResource($sleep->load('creator'));
    }

    public function destroy(Request $request, SleepSession $sleep)
    {
        $this->ensureOwnsRecord($request, $sleep);

        $sleep->delete();

        return response()->noContent();
    }
}
