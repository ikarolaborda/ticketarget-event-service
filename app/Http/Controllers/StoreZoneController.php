<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreZoneRequest;
use App\Models\Venue;
use App\Models\VenueZone;
use App\Services\ZonePresenter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class StoreZoneController
{
    public function __construct(private ZonePresenter $presenter) {}

    public function __invoke(StoreZoneRequest $request, Venue $venue): JsonResponse
    {
        $zone = new VenueZone;
        $zone->venue_id = $venue->id;
        $this->presenter->fill($zone, $request->validated());
        $zone->save();

        return response()->json(['data' => $this->presenter->present($zone)], Response::HTTP_CREATED);
    }
}
