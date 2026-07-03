<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Ticket extends Model
{
    use HasFactory;
    use HasUuids;

    public const string STATUS_AVAILABLE = 'available';

    public const string STATUS_UNAVAILABLE = 'unavailable';

    public const string STATUS_BOOKED = 'booked';

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
