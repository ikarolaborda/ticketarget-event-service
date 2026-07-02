<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ticket> */
final class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'seat' => strtoupper($this->faker->bothify('?##')),
            'price' => $this->faker->randomFloat(2, 50, 1500),
            'type' => $this->faker->randomElement(['standard', 'vip', 'premium']),
            'status' => Ticket::STATUS_AVAILABLE,
        ];
    }
}
