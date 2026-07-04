<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateZoneTicketsRequest extends FormRequest
{
    public const string PRICE = 'price';

    public const string TYPE = 'type';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            self::PRICE => [
                'required',
                'numeric',
                'min:0',
            ],
            self::TYPE => [
                'nullable',
                'string',
                'max:32',
            ],
        ];
    }
}
