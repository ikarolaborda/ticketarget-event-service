<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\JsonResponse;

final class ListVenuesController
{
    public function __invoke(): JsonResponse
    {
        $venues = Venue::query()
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'address', 'capacity']);

        return response()->json(['data' => $venues]);
    }
}
