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

    private const FIELDS = ['name', 'description', 'type', 'artist', 'status', 'date', 'venue_id'];

    /**
     * @param array<string, mixed> $attributes
     */
    public function execute(array $attributes, ?Event $event = null): Event
    {
        $isNew = $event === null;
        $event ??= new Event();

        // Only keys present in the validated payload are assigned, preserving
        // the partial-update semantics of the 'sometimes' rules (absent means
        // untouched; an explicit null stays assignable).
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $attributes)) {
                $event->{$field} = $attributes[$field];
            }
        }

        $event->save();

        if (! $isNew) {
            $this->catalog->forget($event->id);
        }

        return $event->refresh()->load('venue');
    }
}
