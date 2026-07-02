<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

final class UpdateEventRequest extends StoreEventRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:64'],
            'artist' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['draft', 'published', 'cancelled'])],
            'date' => ['sometimes', 'date'],
            'venue_id' => ['sometimes', 'uuid', 'exists:venues,id'],
        ];
    }
}
