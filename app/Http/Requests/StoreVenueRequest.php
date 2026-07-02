<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:64'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:128'],
            'capacity' => ['required', 'integer', 'min:1'],
            'seat_map' => ['nullable', 'array'],
        ];
    }
}
