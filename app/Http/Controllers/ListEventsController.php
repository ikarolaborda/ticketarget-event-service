<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Services\EventCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class ListEventsController
{
    public function __construct(private EventCatalog $catalog)
    {
    }

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) min(max($request->integer('per_page', 20), 1), 100);

        return EventResource::collection($this->catalog->paginate($perPage));
    }
}
