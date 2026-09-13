<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreDiaperChangeRequest;
use App\Http\Resources\DiaperChangeResource;
use App\Models\Baby;
use App\Models\DiaperChange;
use Illuminate\Http\Request;

class DiaperChangeController extends BabyScopedApiController
{
    /**
     * Stool colors that pediatric guidance flags as needing medical attention
     * (pale/clay/white can signal a liver/biliary problem, red can signal blood).
     */
    private const ALARM_COLOR_KEYWORDS = ['red', 'pale', 'white', 'clay', 'grey', 'gray'];

    /**
     * Black/dark-green stool is normal meconium for the first few days of
     * life, but a genuine warning sign afterwards.
     */
    private const MECONIUM_ALARM_KEYWORDS = ['black'];
    private const MECONIUM_GRACE_DAYS = 5;

    public function index(Request $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $query = $baby->diaperChanges()->with('creator')->orderByDesc('occurred_at');

        if ($request->filled('from')) {
            $query->where('occurred_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('occurred_at', '<=', $request->date('to'));
        }

        return DiaperChangeResource::collection($query->paginate(50));
    }

    public function store(StoreDiaperChangeRequest $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        $data = $request->validated();
        $data['flagged_for_doctor'] = $this->isAlarmColor($baby, $data['stool_color_name'] ?? null);
        $data['created_by'] = $request->user()->id;

        $duplicate = $this->findPossibleDuplicate(
            $baby->diaperChanges()->where('client_uuid', '!=', $data['client_uuid']),
            'occurred_at',
            new \DateTimeImmutable($data['occurred_at'])
        );

        $diaper = $baby->diaperChanges()->updateOrCreate(
            ['client_uuid' => $data['client_uuid']],
            $data
        );
        $diaper->setRelation('creator', $request->user());

        if ($duplicate) {
            $diaper->possible_duplicate_of = $duplicate->id;
        }

        return new DiaperChangeResource($diaper);
    }

    public function update(Request $request, DiaperChange $diaper)
    {
        $this->ensureOwnsRecord($request, $diaper);

        $data = $request->validate([
            'stool_color_name' => ['nullable', 'string', 'max:50'],
            'stool_color_hex' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('stool_color_name', $data)) {
            $data['flagged_for_doctor'] = $this->isAlarmColor($diaper->baby, $data['stool_color_name']);
        }

        $diaper->update($data);

        return new DiaperChangeResource($diaper);
    }

    public function destroy(Request $request, DiaperChange $diaper)
    {
        $this->ensureOwnsRecord($request, $diaper);

        $diaper->delete();

        return response()->noContent();
    }

    private function isAlarmColor(Baby $baby, ?string $colorName): bool
    {
        if (! $colorName) {
            return false;
        }

        $colorName = strtolower($colorName);

        foreach (self::ALARM_COLOR_KEYWORDS as $keyword) {
            if (str_contains($colorName, $keyword)) {
                return true;
            }
        }

        $ageInDays = $baby->birth_date->diffInDays(now());

        if ($ageInDays > self::MECONIUM_GRACE_DAYS) {
            foreach (self::MECONIUM_ALARM_KEYWORDS as $keyword) {
                if (str_contains($colorName, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }
}
