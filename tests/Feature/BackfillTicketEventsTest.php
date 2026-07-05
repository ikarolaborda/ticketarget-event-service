<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\Venue;
use App\Services\OutboxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BackfillTicketEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite') {
            throw new \RuntimeException('Refusing to refresh a non-sqlite database.');
        }
    }

    public function test_it_requires_a_cutoff(): void
    {
        $this->artisan('catalog:backfill-ticket-events')->assertFailed();
    }

    public function test_it_emits_zone_and_manual_remainder_events_for_pre_outbox_tickets(): void
    {
        $event = $this->createEvent();

        // Pre-outbox history: a generated zone and two manual tickets, none
        // of which have outbox rows.
        $zoneId = '99999999-8888-7777-6666-555555555555';
        $this->createTicket($event->id, 'Z-0001', $zoneId);
        $this->createTicket($event->id, 'Z-0002', $zoneId);
        $this->createTicket($event->id, 'M01');
        $this->createTicket($event->id, 'M02');

        $this->artisan('catalog:backfill-ticket-events', ['--cutoff' => now()->toIso8601String()])
            ->assertSuccessful();

        $rows = DB::table('catalog_outbox_messages')->orderBy('event_key')->get();
        $this->assertCount(2, $rows);

        $manual = json_decode((string) $rows[0]->payload, true);
        $this->assertStringStartsWith('ticket.generated:manual:backfill:'.$event->id, $rows[0]->event_key);
        $this->assertSame(2, $manual['count']);
        $this->assertNull($manual['zone_id']);
        $this->assertArrayNotHasKey('tickets', $manual);

        $zone = json_decode((string) $rows[1]->payload, true);
        $this->assertSame('ticket.generated:zone:'.$zoneId, $rows[1]->event_key);
        $this->assertSame(2, $zone['count']);
    }

    public function test_it_subtracts_manual_tickets_already_announced_by_live_outbox_rows(): void
    {
        $event = $this->createEvent();

        // One pre-outbox manual ticket, then one announced through the live
        // pipe (outbox row written in the same transaction as the ticket).
        $this->createTicket($event->id, 'M01');
        $this->createTicket($event->id, 'M02');
        app(OutboxWriter::class)->write('event', $event->id, 'ticket.generated', 'ticket.generated:manual:req-1', [
            'event_id' => $event->id,
            'zone_id' => null,
            'count' => 1,
        ]);

        $this->artisan('catalog:backfill-ticket-events', ['--cutoff' => now()->toIso8601String()])
            ->assertSuccessful();

        $backfill = DB::table('catalog_outbox_messages')
            ->where('event_key', 'like', 'ticket.generated:manual:backfill:%')
            ->sole();

        $payload = json_decode((string) $backfill->payload, true);
        $this->assertSame(1, $payload['count']);
    }

    public function test_reruns_emit_nothing_new(): void
    {
        $event = $this->createEvent();
        $this->createTicket($event->id, 'M01');

        $cutoff = now()->toIso8601String();
        $this->artisan('catalog:backfill-ticket-events', ['--cutoff' => $cutoff])->assertSuccessful();
        $this->assertSame(1, DB::table('catalog_outbox_messages')->count());

        // Same cutoff: identical key, insertOrIgnore no-op.
        $this->artisan('catalog:backfill-ticket-events', ['--cutoff' => $cutoff])->assertSuccessful();
        $this->assertSame(1, DB::table('catalog_outbox_messages')->count());

        // Later cutoff: the prior backfill row is subtracted, remainder zero.
        $this->travel(10)->minutes();
        $this->artisan('catalog:backfill-ticket-events', ['--cutoff' => now()->toIso8601String()])
            ->assertSuccessful();
        $this->assertSame(1, DB::table('catalog_outbox_messages')->count());
    }

    public function test_manual_tickets_created_after_the_cutoff_are_left_to_the_live_pipe(): void
    {
        $event = $this->createEvent();
        $this->createTicket($event->id, 'M01');

        $cutoff = now()->toIso8601String();

        $this->travel(5)->minutes();
        $this->createTicket($event->id, 'M02');

        $this->artisan('catalog:backfill-ticket-events', ['--cutoff' => $cutoff])->assertSuccessful();

        $backfill = DB::table('catalog_outbox_messages')
            ->where('event_key', 'like', 'ticket.generated:manual:backfill:%')
            ->sole();

        $payload = json_decode((string) $backfill->payload, true);
        $this->assertSame(1, $payload['count']);
    }

    private function createEvent(): Event
    {
        $venue = new Venue;
        $venue->name = 'Backfill Hall';
        $venue->address = 'Rua Tres, 3';
        $venue->city = 'Recife';
        $venue->capacity = 500;
        $venue->save();

        $event = new Event;
        $event->name = 'Backfill Night';
        $event->venue_id = $venue->id;
        $event->date = now()->addDays(30);
        $event->save();

        return $event;
    }

    private function createTicket(string $eventId, string $seat, ?string $zoneId = null): Ticket
    {
        $ticket = new Ticket;
        $ticket->event_id = $eventId;
        $ticket->seat = $seat;
        $ticket->price = '50.00';
        $ticket->type = 'standard';
        $ticket->status = Ticket::STATUS_AVAILABLE;
        $ticket->zone_id = $zoneId;
        $ticket->save();

        return $ticket;
    }
}
