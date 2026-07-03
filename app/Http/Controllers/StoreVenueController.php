<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreVenueRequest;
use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class StoreVenueController
{
    public function __invoke(StoreVenueRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $venue = new Venue();
        $venue->name = $validated['name'];
        $venue->description = $validated['description'] ?? null;
        $venue->type = $validated['type'] ?? null;
        $venue->address = $validated['address'];
        $venue->city = $validated['city'];
        $venue->capacity = $validated['capacity'];
        $venue->seat_map = $validated['seat_map'] ?? null;
        $venue->save();

        return VenueResource::make($venue)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
