<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketsRequest;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\EventCatalog;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class StoreTicketsController
{
    public function __construct(private EventCatalog $catalog)
    {
    }

    public function __invoke(StoreTicketsRequest $request, Event $event): JsonResponse
    {
        $rows = $request->validated('tickets');

        foreach ($rows as $row) {
            $ticket = new Ticket();
            $ticket->event_id = $event->id;
            $ticket->seat = $row['seat'];
            $ticket->price = $row['price'];
            $ticket->type = $row['type'] ?? 'standard';
            $ticket->status = Ticket::STATUS_AVAILABLE;
            $ticket->save();
        }

        $this->catalog->forget($event->id);

        return response()->json(['created' => count($rows)], Response::HTTP_CREATED);
    }
}
