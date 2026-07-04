<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreZoneRequest;
use App\Models\Ticket;
use App\Models\Venue;
use App\Models\VenueZone;
use App\Services\ZonePresenter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class UpdateZoneController
{
    public function __construct(private ZonePresenter $presenter) {}

    public function __invoke(StoreZoneRequest $request, Venue $venue, VenueZone $zone): JsonResponse
    {
        abort_unless($zone->venue_id === $venue->id, Response::HTTP_NOT_FOUND);

        $validated = $request->validated();

        $hasTickets = Ticket::query()->where('zone_id', $zone->id)->exists();

        // Issued inventory pins the seat topology; presentation fields
        // (name, color, geometry, ordering) stay editable.
        if ($hasTickets && $this->presenter->changesTopology($zone, $validated)) {
            return response()->json(
                ['message' => 'This zone already has generated tickets; its kind and seat counts can no longer change.'],
                Response::HTTP_CONFLICT
            );
        }

        $this->presenter->fill($zone, $validated);
        $zone->save();

        return response()->json(['data' => $this->presenter->present($zone)]);
    }
}
