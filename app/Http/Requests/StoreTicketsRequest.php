<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tickets' => ['required', 'array', 'min:1', 'max:5000'],
            'tickets.*.seat' => ['required', 'string', 'max:32'],
            'tickets.*.price' => ['required', 'numeric', 'min:0'],
            'tickets.*.type' => ['nullable', 'string', 'max:32'],
        ];
    }
}
