<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VenueZone extends Model
{
    use HasUuids;

    public const string KIND_SEATED = 'seated';

    public const string KIND_STANDING = 'standing';

    protected function casts(): array
    {
        return [
            'geometry' => 'array',
            'rows' => 'integer',
            'seats_per_row' => 'integer',
            'capacity' => 'integer',
            'color_index' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function totalSeats(): int
    {
        if ($this->kind === self::KIND_STANDING) {
            return (int) $this->capacity;
        }

        return (int) $this->rows * (int) $this->seats_per_row;
    }

    /**
     * Seat labels embed this prefix, so it must stay deterministic; renames
     * after generation do not rewrite already-issued labels. Multi-word names
     * shrink to initials ("Cadeiras Oeste" → CO) so sibling zones sharing a
     * first word cannot truncate to the same prefix.
     */
    public function labelPrefix(): string
    {
        $words = preg_split('/\s+/', trim($this->name)) ?: [];
        $clean = array_values(array_filter(array_map(
            static fn (string $word): string => strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $word)),
            $words
        ), static fn (string $word): bool => $word !== ''));

        if (count($clean) > 1) {
            return implode('', array_map(static fn (string $word): string => $word[0], $clean));
        }

        $single = $clean[0] ?? '';

        return $single !== '' ? substr($single, 0, 8) : 'ZONE';
    }
}
