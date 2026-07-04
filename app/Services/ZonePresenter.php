<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\StoreZoneRequest;
use App\Models\VenueZone;

final readonly class ZonePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(VenueZone $zone): array
    {
        return [
            'id' => $zone->id,
            'name' => $zone->name,
            'kind' => $zone->kind,
            'rows' => $zone->rows,
            'seats_per_row' => $zone->seats_per_row,
            'capacity' => $zone->capacity,
            'total_seats' => $zone->totalSeats(),
            'color_index' => $zone->color_index,
            'geometry' => $zone->geometry,
            'sort_order' => $zone->sort_order,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function fill(VenueZone $zone, array $validated): void
    {
        $zone->name = $validated[StoreZoneRequest::NAME];
        $zone->kind = $validated[StoreZoneRequest::KIND];
        $zone->rows = $validated[StoreZoneRequest::ROWS] ?? null;
        $zone->seats_per_row = $validated[StoreZoneRequest::SEATS_PER_ROW] ?? null;
        $zone->capacity = $validated[StoreZoneRequest::CAPACITY] ?? null;
        $zone->color_index = $validated[StoreZoneRequest::COLOR_INDEX];
        $zone->geometry = $validated[StoreZoneRequest::GEOMETRY];
        $zone->sort_order = $validated[StoreZoneRequest::SORT_ORDER] ?? 0;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function changesTopology(VenueZone $zone, array $validated): bool
    {
        return $validated[StoreZoneRequest::KIND] !== $zone->kind
            || (int) ($validated[StoreZoneRequest::ROWS] ?? 0) !== (int) $zone->rows
            || (int) ($validated[StoreZoneRequest::SEATS_PER_ROW] ?? 0) !== (int) $zone->seats_per_row
            || (int) ($validated[StoreZoneRequest::CAPACITY] ?? 0) !== (int) $zone->capacity;
    }
}
