<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketsRequest;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\EventCatalog;
use App\Services\EventSearchPayload;
use App\Services\OutboxWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final readonly class StoreTicketsController
{
    public function __construct(
        private EventCatalog $catalog,
        private OutboxWriter $outbox,
        private EventSearchPayload $searchPayload,
    ) {}

    public function __invoke(StoreTicketsRequest $request, Event $event): JsonResponse
    {
        $rows = $request->validated('tickets');

        // The gateway-injected request id doubles as the idempotency key, so a
        // retried identical request cannot enqueue a duplicate event.
        $requestId = (string) $request->header('X-Request-Id', '');
        $eventKey = $requestId !== ''
            ? 'ticket.generated:manual:'.$requestId
            : 'ticket.generated:manual:'.Str::uuid();

        DB::transaction(function () use ($rows, $event, $eventKey): void {
            $created = [];

            foreach ($rows as $row) {
                $ticket = new Ticket;
                $ticket->event_id = $event->id;
                $ticket->seat = $row['seat'];
                $ticket->price = $row['price'];
                $ticket->type = $row['type'] ?? 'standard';
                $ticket->status = Ticket::STATUS_AVAILABLE;
                $ticket->save();

                $created[] = [
                    'id' => $ticket->id,
                    'seat' => $ticket->seat,
                    'price' => $ticket->price,
                    'type' => $ticket->type,
                ];
            }

            $this->outbox->write('event', $event->id, 'ticket.generated', $eventKey, [
                'event_id' => $event->id,
                'zone_id' => null,
                'count' => count($created),
                'tickets' => $created,
            ]);

            // New tickets can change min_price; re-emit the event state inside
            // the same transaction so the search payload sees the fresh rows.
            $occurredAt = now()->format('Y-m-d\TH:i:s.uP');

            $this->outbox->write(
                'event',
                $event->id,
                'event.updated',
                'event.updated:'.$event->id.':'.$occurredAt,
                $this->searchPayload->build($event, $occurredAt),
            );
        });

        $this->catalog->forget($event->id);

        return response()->json(['created' => count($rows)], Response::HTTP_CREATED);
    }
}
