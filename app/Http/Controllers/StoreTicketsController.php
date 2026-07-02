<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketsRequest;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\EventCatalog;
use Illuminate\Http\JsonResponse;

final readonly class StoreTicketsController
{
    public function __construct(private EventCatalog $catalog)
    {
    }

    public function __invoke(StoreTicketsRequest $request, Event $event): JsonResponse
    {
        $rows = array_map(static fn (array $t): array => [
            'event_id' => $event->id,
            'seat' => $t['seat'],
            'price' => $t['price'],
            'type' => $t['type'] ?? 'standard',
            'status' => Ticket::STATUS_AVAILABLE,
        ], $request->validated('tickets'));

        $event->tickets()->createMany($rows);
        $this->catalog->forget($event->id);

        return response()->json(['created' => count($rows)], 201);
    }
}
