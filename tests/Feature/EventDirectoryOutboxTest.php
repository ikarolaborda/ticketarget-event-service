<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class EventDirectoryOutboxTest extends TestCase
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

    public function test_creating_an_event_enqueues_event_created(): void
    {
        $venue = $this->createVenue();

        $eventId = $this->postJson('/events', [
            'name' => 'Directory Night',
            'status' => 'published',
            'date' => now()->addDays(30)->toIso8601String(),
            'venue_id' => $venue->id,
        ], $this->adminHeaders())->assertCreated()->json('data.id');

        $row = DB::table('catalog_outbox_messages')->sole();
        $payload = json_decode((string) $row->payload, true);

        $this->assertSame('event.created', $row->event_type);
        $this->assertSame('event.created:'.$eventId, $row->event_key);
        $this->assertSame($eventId, $payload['event_id']);
        $this->assertSame('Directory Night', $payload['name']);
        $this->assertNotNull($payload['date']);
        // Emission-time microseconds, not the second-precision updated_at.
        $this->assertMatchesRegularExpression('/\.\d{6}/', $payload['occurred_at']);
    }

    public function test_updating_an_event_enqueues_event_updated(): void
    {
        $event = $this->createEvent();

        $this->putJson('/events/'.$event->id, [
            'name' => 'Renamed Night',
        ], $this->adminHeaders())->assertOk();

        $row = DB::table('catalog_outbox_messages')
            ->where('event_type', 'event.updated')
            ->sole();
        $payload = json_decode((string) $row->payload, true);

        $this->assertStringStartsWith('event.updated:'.$event->id.':', $row->event_key);
        $this->assertSame('Renamed Night', $payload['name']);
        $this->assertSame($payload['occurred_at'], substr($row->event_key, strlen('event.updated:'.$event->id.':')));
    }

    public function test_backfill_enqueues_every_event_and_reruns_dedupe(): void
    {
        $this->createEvent('Backfill One');
        $this->createEvent('Backfill Two');

        $this->artisan('catalog:backfill-event-directory')->assertSuccessful();

        $this->assertSame(2, DB::table('catalog_outbox_messages')->where('event_type', 'event.updated')->count());

        // Deterministic keys: a re-run enqueues nothing new.
        $this->artisan('catalog:backfill-event-directory')->assertSuccessful();

        $this->assertSame(2, DB::table('catalog_outbox_messages')->where('event_type', 'event.updated')->count());
    }

    private function createVenue(): Venue
    {
        $venue = new Venue;
        $venue->name = 'Directory Hall';
        $venue->address = 'Rua Tres, 3';
        $venue->city = 'Recife';
        $venue->capacity = 300;
        $venue->save();

        return $venue;
    }

    private function createEvent(string $name = 'Directory Night'): Event
    {
        $event = new Event;
        $event->name = $name;
        $event->venue_id = $this->createVenue()->id;
        $event->date = now()->addDays(30);
        $event->save();

        return $event;
    }

    /**
     * @return array<string, string>
     */
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
