<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Venue> */
final class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        $capacity = $this->faker->numberBetween(500, 50000);

        return [
            'name' => $this->faker->company().' Arena',
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['arena', 'stadium', 'theater', 'club']),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'capacity' => $capacity,
            'seat_map' => ['sections' => $this->faker->numberBetween(2, 10)],
        ];
    }
}
