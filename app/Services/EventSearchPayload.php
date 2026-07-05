<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\Venue;

/**
 * Canonical event-carried-state payload for event.created/event.updated.
 * Every emission path (upsert, ticket generation, backfill) builds through
 * here so the search worker can construct its index document from the payload
 * alone — no cross-context database read. Venue identity is embedded because
 * venues are create-only, and min_price is computed inside the caller's
 * transaction so freshly inserted tickets are already visible.
 */
final readonly class EventSearchPayload
{
    public const int SCHEMA_VERSION = 2;

    /**
     * @return array<string, mixed>
     */
    public function build(Event $event, string $occurredAt): array
    {
        $venue = Venue::query()->find($event->venue_id);
        $minPrice = Ticket::query()->where('event_id', $event->id)->min('price');

        return [
            'event_id' => $event->id,
            'name' => $event->name,
            'description' => $event->description,
            'type' => $event->type,
            'artist' => $event->artist,
            'status' => $event->status,
            'date' => $event->date?->toIso8601String(),
            'venue_name' => $venue?->name,
            'venue_city' => $venue?->city,
            'min_price' => $minPrice !== null ? (float) $minPrice : null,
            'schema_version' => self::SCHEMA_VERSION,
            'occurred_at' => $occurredAt,
        ];
    }
}
