<?php

namespace Database\Factories;

use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourtSlot>
 */
class CourtSlotFactory extends Factory
{
    /**
     * A recurring weekly slot: a court, a day-of-week, and a one-hour window.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hour = fake()->numberBetween(8, 20);

        return [
            'court_id'    => Court::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time'  => sprintf('%02d:00:00', $hour),
            'end_time'    => sprintf('%02d:00:00', $hour + 1),
        ];
    }
}
