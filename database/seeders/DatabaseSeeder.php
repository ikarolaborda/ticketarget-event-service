<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\Venue;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Venue::factory(5)->create()->each(function (Venue $venue): void {
            Event::factory(3)->for($venue)->create()->each(function (Event $event): void {
                // Deterministic seats (A01..D10) guarantee uniqueness per event,
                // unlike random labels which collide on the (event_id, seat) index.
                foreach (range(1, 40) as $n) {
                    Ticket::factory()->for($event)->create([
                        'seat' => sprintf('%s%02d', chr(65 + intdiv($n - 1, 10)), ($n - 1) % 10 + 1),
                    ]);
                }
            });
        });
    }
}
