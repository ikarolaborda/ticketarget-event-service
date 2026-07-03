<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Cache-first read model for the event catalog. Reads hit Redis first, then the
 * Postgres replica, keeping the hot "view event" path off the primary.
 */
final readonly class EventCatalog
{
    private const int TTL = 60;

    public function __construct(private Cache $cache) {}

    public function find(string $eventId): ?Event
    {
        return $this->cache->remember(
            "event:{$eventId}",
            self::TTL,
            fn (): ?Event => Event::query()->with(['venue', 'tickets'])->find($eventId),
        );
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Event::query()
            ->where('status', 'published')
            ->with('venue')
            ->orderBy('date')
            ->paginate($perPage);
    }

    public function forget(string $eventId): void
    {
        $this->cache->forget("event:{$eventId}");
    }
}
