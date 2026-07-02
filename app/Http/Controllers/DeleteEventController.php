<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteEvent;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

final readonly class DeleteEventController
{
    public function __construct(private DeleteEvent $deleteEvent)
    {
    }

    public function __invoke(Event $event): JsonResponse
    {
        $this->deleteEvent->execute($event);

        return response()->json(status: 204);
    }
}
