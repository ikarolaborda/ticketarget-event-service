<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpsertEvent;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

final readonly class UpdateEventController
{
    public function __construct(private UpsertEvent $upsertEvent) {}

    public function __invoke(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $updated = $this->upsertEvent->execute($request->validated(), $event);

        return EventResource::make($updated)->response();
    }
}
