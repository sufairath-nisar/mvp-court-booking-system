<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Court>
 */
class CourtFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'        => fake()->streetName() . ' Court',
            'location'    => fake()->city(),
            'sport_type'  => fake()->randomElement(['tennis', 'football', 'badminton', 'basketball']),
            'hourly_rate' => fake()->randomFloat(2, 10, 100),
            'is_active'   => true,
        ];
    }
}
