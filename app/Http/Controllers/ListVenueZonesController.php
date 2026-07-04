<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Models\VenueZone;
use App\Services\ZonePresenter;
use Illuminate\Http\JsonResponse;

final readonly class ListVenueZonesController
{
    public function __construct(private ZonePresenter $presenter) {}

    public function __invoke(Venue $venue): JsonResponse
    {
        $zones = VenueZone::query()
            ->where('venue_id', $venue->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (VenueZone $zone): array => $this->presenter->present($zone));

        return response()->json(['data' => $zones]);
    }
}
