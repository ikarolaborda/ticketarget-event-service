<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Services\EventCatalog;
use Illuminate\Http\JsonResponse;

final readonly class ShowEventController
{
    public function __construct(private EventCatalog $catalog)
    {
    }

    public function __invoke(string $event): JsonResponse
    {
        $model = $this->catalog->find($event);

        abort_if($model === null, 404, 'Event not found');

        return EventResource::make($model)->response();
    }
}
