<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreVenueRequest;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;

final class StoreVenueController
{
    public function __invoke(StoreVenueRequest $request): JsonResponse
    {
        $venue = Venue::query()->create($request->validated());

        return response()->json(['data' => $venue], 201);
    }
}
