<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\EventHasLiveBookingsException;
use App\Models\Event;
use App\Services\EventCatalog;
use App\Services\EventSearchPayload;
use App\Services\OutboxWriter;
use Illuminate\Support\Facades\DB;

final readonly class DeleteEvent
{
    public function __construct(private EventCatalog $catalog, private OutboxWriter $outbox) {}

    public function execute(Event $event): void
    {
        if ($this->hasLiveBookings($event)) {
            throw new EventHasLiveBookingsException;
        }

        $id = $event->id;

        DB::transaction(function () use ($event, $id): void {
            $event->delete();

            // The search index must drop the document; the booking event
            // directory deliberately ignores this type (purchase-time snapshot
            // semantics keep last-known name/date).
            $occurredAt = now()->format('Y-m-d\TH:i:s.uP');

            $this->outbox->write('event', $id, 'event.deleted', 'event.deleted:'.$id, [
                'event_id' => $id,
                'schema_version' => EventSearchPayload::SCHEMA_VERSION,
                'occurred_at' => $occurredAt,
            ]);
        });

        $this->catalog->forget($id);
    }

    /**
     * The bookings table is booking-service-owned but lives in the shared
     * database (same data plane as the users table). Only live money states
     * block deletion; refunded/orphaned rows never do.
     */
    private function hasLiveBookings(Event $event): bool
    {
        return DB::table('bookings')
            ->join('tickets', 'tickets.id', '=', 'bookings.ticket_id')
            ->where('tickets.event_id', $event->id)
            ->whereIn('bookings.status', ['paid', 'refund_pending'])
            ->exists();
    }
}
