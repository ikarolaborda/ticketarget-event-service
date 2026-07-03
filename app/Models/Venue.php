<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Venue extends Model
{
    use HasFactory;
    use HasUuids;


    protected function casts(): array
    {
        return ['seat_map' => 'array', 'capacity' => 'integer'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
