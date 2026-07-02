<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
final class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'artist' => $this->artist,
            'status' => $this->status,
            'date' => $this->date?->toIso8601String(),
            'venue' => $this->whenLoaded('venue', fn () => [
                'id' => $this->venue->id,
                'name' => $this->venue->name,
                'city' => $this->venue->city,
                'address' => $this->venue->address,
            ]),
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
        ];
    }
}
