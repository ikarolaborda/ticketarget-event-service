<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Venue;
use App\Models\VenueZone;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class DeleteZoneController
{
    public function __invoke(Venue $venue, VenueZone $zone): JsonResponse
    {
        abort_unless($zone->venue_id === $venue->id, Response::HTTP_NOT_FOUND);

        if (Ticket::query()->where('zone_id', $zone->id)->exists()) {
            return response()->json(
                ['message' => 'This zone has generated tickets and cannot be deleted.'],
                Response::HTTP_CONFLICT
            );
        }

        $zone->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
