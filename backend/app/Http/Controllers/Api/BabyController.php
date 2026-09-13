<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreBabyRequest;
use App\Http\Resources\BabyResource;
use App\Models\Baby;
use Illuminate\Http\Request;

class BabyController extends BabyScopedApiController
{
    public function index(Request $request)
    {
        return BabyResource::collection(
            $request->user()->babies()->orderBy('birth_date')->get()
        );
    }

    public function store(StoreBabyRequest $request)
    {
        $baby = Baby::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);
        $baby->users()->attach($request->user()->id);

        return new BabyResource($baby);
    }

    public function show(Request $request, Baby $baby)
    {
        $this->ensureOwnsBaby($request, $baby);

        return new BabyResource($baby);
    }
}
