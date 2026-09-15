<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreFeedingSessionRequest;
use App\Http\Requests\UpdateFeedingSessionRequest;
use App\Http\Resources\FeedingSessionResource;
use App\Models\Baby;
use App\Models\FeedingSession;
use Illuminate\Http\Request;

class FeedingSessionController extends BabyScopedApiController
{
    public function index(Request $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $query = $baby->feedingSessions()->with('creator')->orderByDesc('started_at');

        if ($request->filled('from')) {
            $query->where('started_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('started_at', '<=', $request->date('to'));
        }

        return FeedingSessionResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(StoreFeedingSessionRequest $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $data = $request->validated();

        // Starting a breastfeed (no ended_at yet) is how caregivers signal
        // "feeding now" to each other, same as sleep. If one is already open,
        // hand that back instead of opening a second one — e.g. a caregiver's
        // screen was stale and didn't know the other had already started it.
        if (empty($data['ended_at'])) {
            $activeSession = $baby->feedingSessions()->whereNull('ended_at')->with('creator')->first();
            if ($activeSession) {
                return new FeedingSessionResource($activeSession);
            }
        }

        $data['created_by'] = $request->user()->id;

        $duplicate = $this->findPossibleDuplicate(
            $baby->feedingSessions()->where('client_uuid', '!=', $data['client_uuid']),
            'started_at',
            new \DateTimeImmutable($data['started_at'])
        );

        $feeding = $baby->feedingSessions()->updateOrCreate(
            ['client_uuid' => $data['client_uuid']],
            $data
        );
        $feeding->setRelation('creator', $request->user());

        if ($duplicate) {
            $feeding->possible_duplicate_of = $duplicate->id;
        }

        return new FeedingSessionResource($feeding);
    }

    public function update(UpdateFeedingSessionRequest $request, FeedingSession $feeding)
    {
        $this->ensureOwnsRecord($request, $feeding);

        $data = $request->validated();

        $feeding->update($data);

        return new FeedingSessionResource($feeding->load('creator'));
    }

    public function destroy(Request $request, FeedingSession $feeding)
    {
        $this->ensureOwnsRecord($request, $feeding);

        $feeding->delete();

        return response()->noContent();
    }
}
