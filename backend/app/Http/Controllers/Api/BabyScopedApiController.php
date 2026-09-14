<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Baby;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

abstract class BabyScopedApiController extends Controller
{
    /**
     * Guard against acting on a baby the authenticated user isn't a caregiver for.
     */
    protected function ensureOwnsBaby(Request $request, Baby $baby): void
    {
        abort_unless($baby->users()->whereKey($request->user()->id)->exists(), 404);
    }

    /**
     * Guard against acting on a record whose baby the authenticated user isn't a caregiver for.
     */
    protected function ensureOwnsRecord(Request $request, Model $record): void
    {
        $this->ensureOwnsBaby($request, $record->baby);
    }

    /**
     * Look for another record of the same kind logged within `$windowSeconds`
     * of `$at`, so the client can warn "this looks like a duplicate entry".
     */
    protected function findPossibleDuplicate(HasMany $query, string $timeColumn, \DateTimeInterface $at, int $windowSeconds = 120): ?Model
    {
        return $query
            ->whereBetween($timeColumn, [
                (clone $at)->modify("-{$windowSeconds} seconds"),
                (clone $at)->modify("+{$windowSeconds} seconds"),
            ])
            ->orderByRaw('ABS(EXTRACT(EPOCH FROM ('.$timeColumn.' - ?)))', [$at])
            ->first();
    }

    /**
     * Page size for index() listings — callers like the charts page need far
     * more than the History page's default 50 to cover a wide date range
     * without silently truncating to the newest 50 rows.
     */
    protected function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 50), 1), 2000);
    }
}
