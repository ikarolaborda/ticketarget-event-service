<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
final class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seat' => $this->seat,
            'price' => $this->price,
            'type' => $this->type,
            'status' => $this->status,
        ];
    }
}
