<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
        $second = Ticket::query()->create([
            'event_id' => $event->id,
            'seat' => 'A02',
            'price' => 100,
            'type' => 'standard',
            'status' => Ticket::STATUS_BOOKED,
        ]);

        $this->booking($ticket, 'refunded');
        $this->booking($second, 'paid');

        $this->deleteEvent($event)->assertStatus(409);
    }

    /** @return array{0: Event, 1: Ticket} */
    private function eventWithTicket(): array
    {
        $venue = Venue::query()->create([
            'name' => 'Arena', 'address' => 'Rua Dois, 2', 'city' => 'Olinda', 'capacity' => 500,
        ]);

        $event = Event::query()->create([
            'name' => 'Deletable Show',
            'status' => 'published',
            'date' => now()->addMonth(),
            'venue_id' => $venue->id,
        ]);

        $ticket = Ticket::query()->create([
            'event_id' => $event->id,
            'seat' => 'A01',
            'price' => 100,
            'type' => 'standard',
            'status' => Ticket::STATUS_BOOKED,
        ]);

        return [$event, $ticket];
    }

    private function booking(Ticket $ticket, string $status): void
    {
        \Illuminate\Support\Facades\DB::table('bookings')->insert([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function deleteEvent(Event $event): \Illuminate\Testing\TestResponse
    {
        $token = User::query()->create([
            'name' => 'CLI Admin',
            'email' => Str::uuid().'@example.com',
            'password' => 'irrelevant',
        ])->createToken('cli', ['events:write'])->plainTextToken;

        return $this->deleteJson('/events/'.$event->id, [], ['Authorization' => 'Bearer '.$token]);
    }
}
