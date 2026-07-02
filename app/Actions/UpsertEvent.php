<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Event;
use App\Services\EventCatalog;

/**
 * Creates or updates an event and invalidates its cached read projection so the
 * next read reflects the write immediately (the search index follows via CDC).
 */
final readonly class UpsertEvent
{
    public function __construct(private EventCatalog $catalog)
    {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function execute(array $attributes, ?Event $event = null): Event
    {
        if ($event === null) {
            $event = Event::query()->create($attributes);
        } else {
            $event->update($attributes);
            $this->catalog->forget($event->id);
        }

        return $event->refresh()->load('venue');
    }
}
