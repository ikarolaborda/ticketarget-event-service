<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\VenueZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreZoneRequest extends FormRequest
{
    public const string NAME = 'name';

    public const string KIND = 'kind';

    public const string ROWS = 'rows';

    public const string SEATS_PER_ROW = 'seats_per_row';

    public const string CAPACITY = 'capacity';

    public const string COLOR_INDEX = 'color_index';

    public const string GEOMETRY = 'geometry';

    public const string SORT_ORDER = 'sort_order';

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
            self::NAME => [
                'required',
                'string',
                'max:64',
            ],
            self::KIND => [
                'required',
                Rule::in([VenueZone::KIND_SEATED, VenueZone::KIND_STANDING]),
            ],
            self::ROWS => [
                'required_if:kind,'.VenueZone::KIND_SEATED,
                'nullable',
                'integer',
                'min:1',
                'max:99',
            ],
            self::SEATS_PER_ROW => [
                'required_if:kind,'.VenueZone::KIND_SEATED,
                'nullable',
                'integer',
                'min:1',
                'max:200',
            ],
            self::CAPACITY => [
                'required_if:kind,'.VenueZone::KIND_STANDING,
                'nullable',
                'integer',
                'min:1',
                'max:100000',
            ],
            self::COLOR_INDEX => [
                'required',
                'integer',
                'min:0',
                'max:5',
            ],
            self::GEOMETRY => [
                'required',
                'array',
            ],
            self::GEOMETRY.'.type' => [
                'required',
                Rule::in(['rect']),
            ],
            self::GEOMETRY.'.x' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
            self::GEOMETRY.'.y' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
            self::GEOMETRY.'.w' => [
                'required',
                'numeric',
                'gt:0',
                'max:100',
            ],
            self::GEOMETRY.'.h' => [
                'required',
                'numeric',
                'gt:0',
                'max:100',
            ],
            self::SORT_ORDER => [
                'nullable',
                'integer',
                'min:0',
                'max:999',
            ],
        ];
    }
}
