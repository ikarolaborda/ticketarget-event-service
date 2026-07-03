<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListVenuesController
{
    public function __invoke(): AnonymousResourceCollection
    {
        $venues = Venue::query()
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'address', 'capacity']);

        return VenueResource::collection($venues);
    }
}
