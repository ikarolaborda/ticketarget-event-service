<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\Venue;
use App\Models\VenueZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class VenueZoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_admin_for_zone_writes_and_keeps_reads_public(): void
    {
        $venue = $this->venue();

        $this->postJson('/venues/'.$venue->id.'/zones', $this->zonePayload())->assertStatus(401);
        $this->getJson('/venues/'.$venue->id.'/zones')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_it_creates_a_seated_zone_with_typed_geometry(): void
    {
        $venue = $this->venue();

        $response = $this->postJson('/venues/'.$venue->id.'/zones', $this->zonePayload(), $this->adminHeaders())
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'VIP Floor')
            ->assertJsonPath('data.total_seats', 6)
            ->assertJsonPath('data.geometry.type', 'rect');

        $this->assertNotNull($response->json('data.id'));
    }

    public function test_it_rejects_geometry_outside_the_normalized_canvas(): void
    {
        $venue = $this->venue();

        $payload = $this->zonePayload();
        $payload['geometry']['x'] = 140;

        $this->postJson('/venues/'.$venue->id.'/zones', $payload, $this->adminHeaders())
            ->assertStatus(422);
    }

    public function test_it_generates_namespaced_seated_tickets_once(): void
    {
        [$event, $zone] = $this->eventWithZone();

        $this->postJson(
            '/events/'.$event->id.'/zones/'.$zone->id.'/tickets',
            ['price' => 120.5],
            $this->adminHeaders()
        )->assertStatus(201)->assertJsonPath('created', 6);

        $seats = Ticket::query()->where('zone_id', $zone->id)->orderBy('seat')->pluck('seat')->all();
        $this->assertSame(['VF-A01', 'VF-A02', 'VF-A03', 'VF-B01', 'VF-B02', 'VF-B03'], $seats);

        // Generate-once per (event, zone): regeneration must 409, not duplicate.
        $this->postJson(
            '/events/'.$event->id.'/zones/'.$zone->id.'/tickets',
            ['price' => 99],
            $this->adminHeaders()
        )->assertStatus(409);

        $this->assertSame(6, Ticket::query()->where('zone_id', $zone->id)->count());
    }

    public function test_it_rejects_generation_when_the_seat_prefix_collides_with_a_sibling_zone(): void
    {
        [$event, $zone] = $this->eventWithZone();

        $sibling = new VenueZone;
        $sibling->venue_id = $zone->venue_id;
        $sibling->name = 'Vaulted Foyer';
        $sibling->kind = VenueZone::KIND_SEATED;
        $sibling->rows = 1;
        $sibling->seats_per_row = 2;
        $sibling->color_index = 2;
        $sibling->geometry = ['type' => 'rect', 'x' => 50, 'y' => 50, 'w' => 10, 'h' => 10];
        $sibling->save();

        $this->postJson('/events/'.$event->id.'/zones/'.$zone->id.'/tickets', ['price' => 10], $this->adminHeaders())
            ->assertStatus(201);

        // "VIP Floor" and "Vaulted Foyer" both shrink to the prefix VF.
        $this->postJson('/events/'.$event->id.'/zones/'.$sibling->id.'/tickets', ['price' => 10], $this->adminHeaders())
            ->assertStatus(409);
    }

    public function test_a_unique_collision_that_slips_past_the_prechecks_returns_conflict(): void
    {
        [$event, $zone] = $this->eventWithZone();

        // Simulates the concurrent-generate race: a zoneless ticket already
        // holds a seat label the generator is about to claim.
        $squatter = new Ticket;
        $squatter->event_id = $event->id;
        $squatter->seat = 'VF-A02';
        $squatter->price = 1;
        $squatter->type = 'standard';
        $squatter->status = 'available';
        $squatter->save();

        $this->postJson('/events/'.$event->id.'/zones/'.$zone->id.'/tickets', ['price' => 10], $this->adminHeaders())
            ->assertStatus(409);

        // The transaction must leave no partial zone inventory behind.
        $this->assertSame(0, Ticket::query()->where('zone_id', $zone->id)->count());
    }

    public function test_it_generates_standing_tickets_from_capacity(): void
    {
        [$event, $zone] = $this->eventWithZone(kind: VenueZone::KIND_STANDING, capacity: 4);

        $this->postJson(
            '/events/'.$event->id.'/zones/'.$zone->id.'/tickets',
            ['price' => 80],
            $this->adminHeaders()
        )->assertStatus(201)->assertJsonPath('created', 4);

        $this->assertSame('PIT-0001', Ticket::query()->where('zone_id', $zone->id)->orderBy('seat')->first()?->seat);
    }

    public function test_zone_deletion_is_blocked_once_tickets_exist(): void
    {
        [$event, $zone] = $this->eventWithZone();
        $venueId = $zone->venue_id;

        $this->postJson('/events/'.$event->id.'/zones/'.$zone->id.'/tickets', ['price' => 10], $this->adminHeaders())
            ->assertStatus(201);

        $this->deleteJson('/venues/'.$venueId.'/zones/'.$zone->id, [], $this->adminHeaders())
            ->assertStatus(409);

        // Presentation edits stay allowed; topology edits are frozen.
        $payload = $this->zonePayload();
        $payload['name'] = 'VIP Floor';
        $payload['color_index'] = 3;
        $this->putJson('/venues/'.$venueId.'/zones/'.$zone->id, $payload, $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.color_index', 3);

        $payload['rows'] = 9;
        $this->putJson('/venues/'.$venueId.'/zones/'.$zone->id, $payload, $this->adminHeaders())
            ->assertStatus(409);
    }

    public function test_event_zones_report_availability_and_from_price_including_sold_out(): void
    {
        [$event, $zone] = $this->eventWithZone();

        $this->postJson('/events/'.$event->id.'/zones/'.$zone->id.'/tickets', ['price' => 50], $this->adminHeaders())
            ->assertStatus(201);

        Ticket::query()->where('zone_id', $zone->id)->limit(4)->get()
            ->each(function (Ticket $ticket): void {
                $ticket->status = 'booked';
                $ticket->save();
            });

        $soldOut = $this->soldOutZone($event);

        $body = $this->getJson('/events/'.$event->id.'/zones')->assertOk()->json('data');

        $this->assertCount(2, $body);
        $byName = collect($body)->keyBy('name');
        $this->assertSame(2, $byName['VIP Floor']['available']);
        $this->assertSame(6, $byName['VIP Floor']['tickets_total']);
        $this->assertSame('50.00', $byName['VIP Floor']['from_price']);
        $this->assertSame(0, $byName[$soldOut->name]['available']);
        $this->assertNull($byName[$soldOut->name]['from_price']);
    }

    private function soldOutZone(Event $event): VenueZone
    {
        $zone = new VenueZone;
        $zone->venue_id = $event->venue_id;
        $zone->name = 'Sold Out Corner';
        $zone->kind = VenueZone::KIND_STANDING;
        $zone->capacity = 2;
        $zone->color_index = 1;
        $zone->geometry = ['type' => 'rect', 'x' => 60, 'y' => 60, 'w' => 20, 'h' => 20];
        $zone->sort_order = 1;
        $zone->save();

        $this->postJson('/events/'.$event->id.'/zones/'.$zone->id.'/tickets', ['price' => 30], $this->adminHeaders())
            ->assertStatus(201);

        Ticket::query()->where('zone_id', $zone->id)->get()->each(function (Ticket $ticket): void {
            $ticket->status = 'booked';
            $ticket->save();
        });

        return $zone;
    }

    /**
     * @return array{0: Event, 1: VenueZone}
     */
    private function eventWithZone(string $kind = VenueZone::KIND_SEATED, int $capacity = 4): array
    {
        $venue = $this->venue();

        $zone = new VenueZone;
        $zone->venue_id = $venue->id;
        $zone->kind = $kind;

        if ($kind === VenueZone::KIND_SEATED) {
            $zone->name = 'VIP Floor';
            $zone->rows = 2;
            $zone->seats_per_row = 3;
        }

        if ($kind === VenueZone::KIND_STANDING) {
            $zone->name = 'Pit';
            $zone->capacity = $capacity;
        }

        $zone->color_index = 0;
        $zone->geometry = ['type' => 'rect', 'x' => 10, 'y' => 10, 'w' => 30, 'h' => 20];
        $zone->save();

        $event = new Event;
        $event->name = 'Zone Show';
        $event->status = 'published';
        $event->date = now()->addMonth();
        $event->venue_id = $venue->id;
        $event->save();

        return [$event, $zone];
    }

    private function venue(): Venue
    {
        $venue = new Venue;
        $venue->name = 'Zone Hall';
        $venue->address = 'Rua Dois, 2';
        $venue->city = 'Recife';
        $venue->capacity = 500;
        $venue->save();

        return $venue;
    }

    private function zonePayload(): array
    {
        return [
            'name' => 'VIP Floor',
            'kind' => VenueZone::KIND_SEATED,
            'rows' => 2,
            'seats_per_row' => 3,
            'capacity' => null,
            'color_index' => 0,
            'geometry' => ['type' => 'rect', 'x' => 10, 'y' => 10, 'w' => 30, 'h' => 20],
            'sort_order' => 0,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function adminHeaders(): array
    {
        $encode = static fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');

        $header = $encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $encode(json_encode([
            'iss' => 'ticketarget-users',
            'sub' => '11111111-2222-3333-4444-555555555555',
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'is_admin' => true,
            'iat' => time(),
            'exp' => time() + 3600,
        ]));
        $signature = $encode(hash_hmac('sha256', $header.'.'.$payload, (string) config('auth_token.secret'), true));

        return ['Authorization' => 'Bearer '.$header.'.'.$payload.'.'.$signature];
    }
}
