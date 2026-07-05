<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Event;
use App\Services\EventCatalog;
use App\Services\OutboxWriter;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates an event and invalidates its cached read projection so the
 * next read reflects the write immediately (the search index follows via CDC).
 */
final readonly class UpsertEvent
{
    public function __construct(private EventCatalog $catalog, private OutboxWriter $outbox) {}

    private const FIELDS = ['name', 'description', 'type', 'artist', 'status', 'date', 'venue_id'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes, ?Event $event = null): Event
    {
        $isNew = $event === null;
        $event ??= new Event;

        // Only keys present in the validated payload are assigned, preserving
        // the partial-update semantics of the 'sometimes' rules (absent means
        // untouched; an explicit null stays assignable).
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $attributes)) {
                $event->{$field} = $attributes[$field];
            }
        }

        DB::transaction(function () use ($event, $isNew): void {
            $event->save();

            // occurred_at is emission time at microsecond precision, NOT
            // updated_at: the column is second-precision, so two updates in
            // one second would otherwise collide on key and ordering in the
            // booking directory projection.
            $occurredAt = now()->format('Y-m-d\TH:i:s.uP');

            $this->outbox->write(
                'event',
                $event->id,
                $isNew ? 'event.created' : 'event.updated',
                $isNew ? 'event.created:'.$event->id : 'event.updated:'.$event->id.':'.$occurredAt,
                [
                    'event_id' => $event->id,
                    'name' => $event->name,
                    'date' => $event->date?->toIso8601String(),
                    'occurred_at' => $occurredAt,
                ],
            );
        });

        if (! $isNew) {
            $this->catalog->forget($event->id);
        }

        return $event->refresh()->load('venue');
    }
}
