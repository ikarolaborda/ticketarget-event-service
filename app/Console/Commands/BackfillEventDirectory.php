<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\EventSearchPayload;
use App\Services\OutboxWriter;
use Illuminate\Console\Command;

/**
 * Seeds the downstream event read models (booking directory, search index)
 * with the current catalog state by re-emitting every event through the
 * outbox. occurred_at is the persisted updated_at, which is strictly older
 * than any live emission that follows a later edit — so replays and
 * overlapping live traffic converge on the newest state. Deterministic keys
 * make re-runs dedupe in the outbox. This is also the repair tool for the
 * search index: a full run rebuilds every document through the live pipeline.
 */
final class BackfillEventDirectory extends Command
{
    protected $signature = 'catalog:backfill-event-directory';

    protected $description = 'Emit event.updated for every event so downstream read models converge';

    public function handle(OutboxWriter $outbox, EventSearchPayload $searchPayload): int
    {
        $emitted = 0;

        Event::query()->orderBy('id')->chunk(200, function ($events) use ($outbox, $searchPayload, &$emitted): void {
            foreach ($events as $event) {
                $occurredAt = ($event->updated_at ?? now())->format('Y-m-d\TH:i:s.uP');

                // The schema version is part of the key so a payload-shape
                // upgrade re-emits every event once; same-shape re-runs still
                // dedupe. Consumers treat replays as no-ops (equal occurred_at).
                $outbox->write(
                    'event',
                    $event->id,
                    'event.updated',
                    'event.updated:backfill:v'.EventSearchPayload::SCHEMA_VERSION.':'.$event->id.':'.$occurredAt,
                    $searchPayload->build($event, $occurredAt),
                );

                $emitted++;
            }
        });

        $this->info(sprintf('Enqueued %d event directory message(s).', $emitted));

        return self::SUCCESS;
    }
}
