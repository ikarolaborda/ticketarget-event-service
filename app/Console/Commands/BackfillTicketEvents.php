<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OutboxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot cutover backfill: announces tickets generated before the outbox
 * existed as ticket.generated events, so the booking capacity read model can
 * be seeded entirely through the event pipe (no cross-context table read).
 *
 * Zone tickets re-emit under the live deterministic key — zones are
 * generate-once, so outbox uniqueness plus consumer-side dedupe collapse any
 * overlap with already-published events. Manual tickets (zone_id null) had
 * request-id keys that cannot be reconstructed, so the remainder up to an
 * explicit --cutoff is emitted as one aggregate event per catalog event;
 * prior backfill rows are always subtracted, making reruns emit nothing.
 * Backfill payloads deliberately omit tickets[] — consumers of the capacity
 * contract only need {event_id, zone_id, count}.
 */
final class BackfillTicketEvents extends Command
{
    protected $signature = 'catalog:backfill-ticket-events {--cutoff= : ISO-8601 upper bound for manual tickets (required)}';

    protected $description = 'Emit ticket.generated events for tickets that predate the outbox';

    public function handle(OutboxWriter $outbox): int
    {
        $cutoffOption = (string) $this->option('cutoff');

        if ($cutoffOption === '') {
            $this->error('A --cutoff timestamp is required (pick a moment in the past; later tickets are covered by the live outbox).');

            return self::INVALID;
        }

        $cutoff = CarbonImmutable::parse($cutoffOption)->utc();
        $cutoffKey = $cutoff->format('Ymd\THis\Z');

        $zoneEmitted = $this->backfillZones($outbox);
        $manualEmitted = $this->backfillManual($outbox, $cutoff, $cutoffKey);

        $this->info(sprintf(
            'Backfill complete: %d zone event(s), %d manual remainder event(s) enqueued.',
            $zoneEmitted,
            $manualEmitted,
        ));

        return self::SUCCESS;
    }

    private function backfillZones(OutboxWriter $outbox): int
    {
        $zones = DB::table('tickets')
            ->whereNotNull('zone_id')
            ->select('event_id', 'zone_id', DB::raw('COUNT(*) AS n'))
            ->groupBy('event_id', 'zone_id')
            ->get();

        $emitted = 0;

        foreach ($zones as $zone) {
            $outbox->write('event', (string) $zone->event_id, 'ticket.generated', 'ticket.generated:zone:'.$zone->zone_id, [
                'event_id' => (string) $zone->event_id,
                'zone_id' => (string) $zone->zone_id,
                'count' => (int) $zone->n,
            ]);
            $emitted++;
        }

        return $emitted;
    }

    private function backfillManual(OutboxWriter $outbox, CarbonImmutable $cutoff, string $cutoffKey): int
    {
        $manualCounts = DB::table('tickets')
            ->whereNull('zone_id')
            ->where('created_at', '<=', $cutoff)
            ->select('event_id', DB::raw('COUNT(*) AS n'))
            ->groupBy('event_id')
            ->pluck('n', 'event_id');

        // Live manual rows share their tickets' created_at (same transaction),
        // so the cutoff bounds both sides consistently. Prior backfill rows
        // are subtracted unconditionally: their emission time is unrelated to
        // the ticket timestamps they cover.
        $announcedRows = DB::table('catalog_outbox_messages')
            ->where('event_type', 'ticket.generated')
            ->where('event_key', 'like', 'ticket.generated:manual:%')
            ->where(function ($query) use ($cutoff): void {
                $query->where('created_at', '<=', $cutoff)
                    ->orWhere('event_key', 'like', 'ticket.generated:manual:backfill:%');
            })
            ->get(['aggregate_id', 'payload']);

        $announced = [];

        foreach ($announcedRows as $row) {
            $payload = json_decode((string) $row->payload, true);
            $count = is_array($payload) && is_numeric($payload['count'] ?? null) ? (int) $payload['count'] : 0;
            $announced[(string) $row->aggregate_id] = ($announced[(string) $row->aggregate_id] ?? 0) + $count;
        }

        $emitted = 0;

        foreach ($manualCounts as $eventId => $total) {
            $remainder = (int) $total - ($announced[(string) $eventId] ?? 0);

            if ($remainder <= 0) {
                continue;
            }

            $outbox->write('event', (string) $eventId, 'ticket.generated', sprintf('ticket.generated:manual:backfill:%s:%s', $eventId, $cutoffKey), [
                'event_id' => (string) $eventId,
                'zone_id' => null,
                'count' => $remainder,
            ]);
            $emitted++;
        }

        return $emitted;
    }
}
