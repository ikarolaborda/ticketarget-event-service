<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class DeleteEventTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite') {
            throw new \RuntimeException('Refusing to refresh a non-sqlite database.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Booking-service owns this table in the shared database; recreate the
        // slice the delete guard queries.
        if (! Schema::hasTable('bookings')) {
            Schema::create('bookings', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('ticket_id');
                $table->string('status')->default('paid');
                $table->timestamps();
            });
        }
    }

    public function test_it_deletes_an_event_with_no_bookings(): void
    {
        [$event] = $this->eventWithTicket();

        $this->deleteEvent($event)->assertStatus(204);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_it_deletes_an_event_whose_bookings_are_all_refunded(): void
    {
        [$event, $ticket] = $this->eventWithTicket();
        $this->booking($ticket, 'refunded');

        $this->deleteEvent($event)->assertStatus(204);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_it_blocks_deletion_when_a_booking_is_paid(): void
    {
        [$event, $ticket] = $this->eventWithTicket();
        $this->booking($ticket, 'paid');

        $this->deleteEvent($event)->assertStatus(409);
        $this->assertDatabaseHas('events', ['id' => $event->id]);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
    }

    public function test_it_blocks_deletion_when_a_refund_is_pending(): void
    {
        [$event, $ticket] = $this->eventWithTicket();
        $this->booking($ticket, 'refund_pending');

        $this->deleteEvent($event)->assertStatus(409);
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_it_blocks_deletion_on_mixed_statuses_when_any_is_live(): void
    {
        [$event, $ticket] = $this->eventWithTicket();
        $second = $this->makeTicket($event->id, 'A02');

        $this->booking($ticket, 'refunded');
        $this->booking($second, 'paid');

        $this->deleteEvent($event)->assertStatus(409);
    }

    /** @return array{0: Event, 1: Ticket} */
    private function eventWithTicket(): array
    {
        $venue = new Venue;
        $venue->name = 'Arena';
        $venue->address = 'Rua Dois, 2';
        $venue->city = 'Olinda';
        $venue->capacity = 500;
        $venue->save();

        $event = new Event;
        $event->name = 'Deletable Show';
        $event->status = 'published';
        $event->date = now()->addMonth();
        $event->venue_id = $venue->id;
        $event->save();

        return [$event, $this->makeTicket($event->id, 'A01')];
    }

    private function makeTicket(string $eventId, string $seat): Ticket
    {
        $ticket = new Ticket;
        $ticket->event_id = $eventId;
        $ticket->seat = $seat;
        $ticket->price = 100;
        $ticket->type = 'standard';
        $ticket->status = Ticket::STATUS_BOOKED;
        $ticket->save();

        return $ticket;
    }

    private function booking(Ticket $ticket, string $status): void
    {
        DB::table('bookings')->insert([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function deleteEvent(Event $event): TestResponse
    {
        $user = new User;
        $user->name = 'CLI Admin';
        $user->email = Str::uuid().'@example.com';
        $user->password = 'irrelevant';
        $user->save();

        $token = $user->createToken('cli', ['events:write'])->plainTextToken;

        return $this->deleteJson('/events/'.$event->id, [], ['Authorization' => 'Bearer '.$token]);
    }
}
