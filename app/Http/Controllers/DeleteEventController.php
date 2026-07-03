<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteEvent;
use App\Exceptions\EventHasLiveBookingsException;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class DeleteEventController
{
    public function __construct(private DeleteEvent $deleteEvent) {}

    public function __invoke(Event $event): JsonResponse
    {
        try {
            $this->deleteEvent->execute($event);
        } catch (EventHasLiveBookingsException) {
            return response()->json([
                'message' => 'This event has live bookings (paid or refund pending) and cannot be deleted. Refund them first.',
            ], Response::HTTP_CONFLICT);
        }

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
