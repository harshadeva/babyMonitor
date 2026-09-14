<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreMilestoneRequest;
use App\Http\Requests\UpdateMilestoneRequest;
use App\Http\Resources\MilestoneResource;
use App\Models\Baby;
use App\Models\Milestone;
use Illuminate\Http\Request;

class MilestoneController extends BabyScopedApiController
{
    public function index(Request $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $query = $baby->milestones()->with('creator')->orderByDesc('occurred_at');

        if ($request->filled('from')) {
            $query->where('occurred_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('occurred_at', '<=', $request->date('to'));
        }

        return MilestoneResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(StoreMilestoneRequest $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $duplicate = $this->findPossibleDuplicate(
            $baby->milestones()->where('client_uuid', '!=', $data['client_uuid']),
            'occurred_at',
            new \DateTimeImmutable($data['occurred_at'])
        );

        $milestone = $baby->milestones()->updateOrCreate(
            ['client_uuid' => $data['client_uuid']],
            $data
        );
        $milestone->setRelation('creator', $request->user());

        if ($duplicate) {
            $milestone->possible_duplicate_of = $duplicate->id;
        }

        return new MilestoneResource($milestone);
    }

    public function update(UpdateMilestoneRequest $request, Milestone $milestone)
    {
        $this->ensureOwnsRecord($request, $milestone);

        $data = $request->validated();

        $milestone->update($data);

        return new MilestoneResource($milestone->load('creator'));
    }

    public function destroy(Request $request, Milestone $milestone)
    {
        $this->ensureOwnsRecord($request, $milestone);

        $milestone->delete();

        return response()->noContent();
    }
}
