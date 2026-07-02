<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpsertEvent;
use App\Http\Requests\StoreEventRequest;
use App\Http\Resources\EventResource;
use Illuminate\Http\JsonResponse;

final readonly class StoreEventController
{
    public function __construct(private UpsertEvent $upsertEvent)
    {
    }

    public function __invoke(StoreEventRequest $request): JsonResponse
    {
        $event = $this->upsertEvent->execute($request->validated());

        return EventResource::make($event)->response()->setStatusCode(201);
    }
}
