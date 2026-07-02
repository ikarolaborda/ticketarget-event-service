<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
final class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Bruno Mars', 'Avenged Sevenfold', 'Roxette', 'NBA House'])
                .' '.$this->faker->city().' '.$this->faker->year(),
            'description' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(['concert', 'sports', 'theater']),
            'artist' => $this->faker->name(),
            'status' => 'published',
            'date' => $this->faker->dateTimeBetween('+1 week', '+1 year'),
            'venue_id' => Venue::factory(),
        ];
    }
}
