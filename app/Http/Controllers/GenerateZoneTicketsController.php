<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GenerateZoneTicketsRequest;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\VenueZone;
use App\Services\EventCatalog;
use App\Services\OutboxWriter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class GenerateZoneTicketsController
{
    public function __construct(
        private EventCatalog $catalog,
        private OutboxWriter $outbox,
    ) {}

    public function __invoke(GenerateZoneTicketsRequest $request, Event $event, VenueZone $zone): JsonResponse
    {
        abort_unless($zone->venue_id === $event->venue_id, Response::HTTP_NOT_FOUND);

        $exists = Ticket::query()
            ->where('event_id', $event->id)
            ->where('zone_id', $zone->id)
            ->exists();

        // Regeneration would re-issue seat identities under sold inventory;
        // the zone is generate-once per event by design.
        if ($exists) {
            return response()->json(
                ['message' => 'Tickets for this zone already exist for this event.'],
                Response::HTTP_CONFLICT
            );
        }

        // Initial-based prefixes can still collide across zones; failing
        // upfront beats a mid-insert unique violation.
        $collides = Ticket::query()
            ->where('event_id', $event->id)
            ->where('seat', 'like', $zone->labelPrefix().'-%')
            ->exists();

        if ($collides) {
            return response()->json(
                ['message' => 'Another zone already uses the seat prefix "'.$zone->labelPrefix().'" for this event — rename this zone first.'],
                Response::HTTP_CONFLICT
            );
        }

        $price = (float) $request->validated(GenerateZoneTicketsRequest::PRICE);
        $type = $request->validated(GenerateZoneTicketsRequest::TYPE) ?? 'standard';

        try {
            $created = $this->generate($event, $zone, $price, $type);
        } catch (UniqueConstraintViolationException) {
            // The exists()/prefix pre-checks are advisory; under a concurrent
            // generate the (event_id, seat) unique index is the real arbiter.
            return response()->json(
                ['message' => 'Ticket generation collided with a concurrent request — check the zone and retry if needed.'],
                Response::HTTP_CONFLICT
            );
        }

        $this->catalog->forget($event->id);

        return response()->json(['created' => $created], Response::HTTP_CREATED);
    }

    private function generate(Event $event, VenueZone $zone, float $price, string $type): int
    {
        return DB::transaction(function () use ($event, $zone, $price, $type): int {
            $created = [];

            foreach ($this->seatLabels($zone) as $seat) {
                $ticket = new Ticket;
                $ticket->event_id = $event->id;
                $ticket->zone_id = $zone->id;
                $ticket->seat = $seat;
                $ticket->price = $price;
                $ticket->type = $type;
                $ticket->status = Ticket::STATUS_AVAILABLE;
                $ticket->save();

                $created[] = [
                    'id' => $ticket->id,
                    'seat' => $ticket->seat,
                    'price' => $ticket->price,
                    'type' => $ticket->type,
                ];
            }

            // Zone generation is generate-once per (event, zone), so the zone
            // id is a natural idempotency key for the integration event.
            $this->outbox->write('event', $event->id, 'ticket.generated', 'ticket.generated:zone:'.$zone->id, [
                'event_id' => $event->id,
                'zone_id' => $zone->id,
                'count' => count($created),
                'tickets' => $created,
            ]);

            return count($created);
        });
    }

    /**
     * @return iterable<string>
     */
    private function seatLabels(VenueZone $zone): iterable
    {
        $prefix = $zone->labelPrefix();

        if ($zone->kind === VenueZone::KIND_STANDING) {
            for ($n = 1; $n <= (int) $zone->capacity; $n++) {
                yield sprintf('%s-%04d', $prefix, $n);
            }

            return;
        }

        for ($r = 0; $r < (int) $zone->rows; $r++) {
            $rowLabel = chr(65 + intdiv($r, 26) - 1).chr(65 + $r % 26);
            $rowLabel = $r < 26 ? chr(65 + $r) : $rowLabel;

            for ($s = 1; $s <= (int) $zone->seats_per_row; $s++) {
                yield sprintf('%s-%s%02d', $prefix, $rowLabel, $s);
            }
        }
    }
}
