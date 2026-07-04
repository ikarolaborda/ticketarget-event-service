<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\VenueZone;
use App\Services\ZonePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * The buyer-facing layout: every zone of the event's venue (zero-availability
 * zones included, so the map can gray them out instead of hiding them) plus
 * per-zone availability and the from-price of what is still purchasable.
 */
final readonly class ListEventZonesController
{
    public function __construct(private ZonePresenter $presenter) {}

    public function __invoke(Event $event): JsonResponse
    {
        $stats = DB::table('tickets')
            ->where('event_id', $event->id)
            ->whereNotNull('zone_id')
            ->select(
                'zone_id',
                DB::raw('COUNT(*) AS total'),
                DB::raw("COUNT(*) FILTER (WHERE status = 'available') AS available"),
                DB::raw("MIN(price) FILTER (WHERE status = 'available') AS from_price")
            )
            ->groupBy('zone_id')
            ->get()
            ->keyBy('zone_id');

        $zones = VenueZone::query()
            ->where('venue_id', $event->venue_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (VenueZone $zone) use ($stats): array {
                $stat = $stats->get($zone->id);

                return $this->presenter->present($zone) + [
                    'tickets_total' => $stat !== null ? (int) $stat->total : 0,
                    'available' => $stat !== null ? (int) $stat->available : 0,
                    'from_price' => $stat?->from_price !== null
                        ? number_format((float) $stat->from_price, 2, '.', '')
                        : null,
                ];
            });

        return response()->json(['data' => $zones]);
    }
}
