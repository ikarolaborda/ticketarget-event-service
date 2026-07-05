<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OutboxTicketEventsTest extends TestCase
{
    use CreatesIdentityTables;
    use RefreshDatabase;

    private const string SECRET = 'test-auth-secret';

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite') {
            throw new \RuntimeException('Refusing to refresh a non-sqlite database.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth_token.secret' => self::SECRET, 'auth_token.issuer' => 'ticketarget-users']);

        $this->createIdentityTables();
    }

    public function test_manual_ticket_creation_enqueues_a_ticket_generated_event(): void
    {
        $event = $this->createEvent();

        $this->postJson('/events/'.$event->id.'/tickets', [
            'tickets' => [
                ['seat' => 'A01', 'price' => 50],
                ['seat' => 'A02', 'price' => 50],
            ],
        ], $this->adminHeaders())->assertCreated();

        $this->assertSame(1, DB::table('outbox_messages')->count());

        $row = DB::table('outbox_messages')->sole();
        $payload = json_decode((string) $row->payload, true);

        $this->assertSame('ticket.generated', $row->event_type);
        $this->assertSame($event->id, $payload['event_id']);
        $this->assertSame(2, $payload['count']);
        $this->assertSame('A01', $payload['tickets'][0]['seat']);
    }

    public function test_a_retried_manual_request_with_the_same_request_id_dedupes(): void
    {
        $event = $this->createEvent();
        $headers = $this->adminHeaders() + ['X-Request-Id' => 'req-123'];

        $this->postJson('/events/'.$event->id.'/tickets', [
            'tickets' => [['seat' => 'B01', 'price' => 10]],
        ], $headers)->assertCreated();

        $this->postJson('/events/'.$event->id.'/tickets', [
            'tickets' => [['seat' => 'B02', 'price' => 10]],
        ], $headers)->assertCreated();

        // Same request id -> same event_key -> one outbox row (retry dedupe).
        $this->assertSame(1, DB::table('outbox_messages')->count());
    }

    public function test_zone_generation_enqueues_one_event_keyed_by_zone(): void
    {
        $event = $this->createEvent();

        $zoneId = $this->postJson('/venues/'.$event->venue_id.'/zones', [
            'name' => 'Pista',
            'kind' => 'standing',
            'capacity' => 5,
            'color_index' => 0,
            'geometry' => ['type' => 'rect', 'x' => 10, 'y' => 10, 'w' => 30, 'h' => 30],
        ], $this->adminHeaders())->assertCreated()->json('data.id');

        $this->postJson('/events/'.$event->id.'/zones/'.$zoneId.'/tickets', [
            'price' => 80,
        ], $this->adminHeaders())->assertCreated();

        $row = DB::table('outbox_messages')->sole();
        $payload = json_decode((string) $row->payload, true);

        $this->assertSame('ticket.generated:zone:'.$zoneId, $row->event_key);
        $this->assertSame($zoneId, $payload['zone_id']);
        $this->assertSame(5, $payload['count']);
        $this->assertCount(5, $payload['tickets']);
    }

    private function createEvent(): Event
    {
        $venue = new Venue;
        $venue->name = 'Outbox Hall';
        $venue->address = 'Rua Dois, 2';
        $venue->city = 'Recife';
        $venue->capacity = 500;
        $venue->save();

        $event = new Event;
        $event->name = 'Outbox Night';
        $event->venue_id = $venue->id;
        $event->date = now()->addDays(30);
        $event->save();

        return $event;
    }

    private function adminHeaders(): array
    {
        $encode = static fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');

        $header = $encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $encode(json_encode([
            'iss' => 'ticketarget-users',
            'sub' => '11111111-2222-3333-4444-555555555555',
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'is_admin' => true,
            'iat' => time(),
            'exp' => time() + 3600,
        ], JSON_THROW_ON_ERROR));
        $signature = $encode(hash_hmac('sha256', $header.'.'.$payload, self::SECRET, true));

        return ['Authorization' => 'Bearer '.$header.'.'.$payload.'.'.$signature];
    }
}
