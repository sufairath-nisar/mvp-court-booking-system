<?php

namespace Database\Factories;

use App\Models\Court;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourtSlot>
 */
class CourtSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hour = fake()->numberBetween(8, 20);

        return [
            'court_id'   => Court::factory(),
            'date'       => Carbon::tomorrow()->format('Y-m-d'),
            'start_time' => sprintf('%02d:00:00', $hour),
            'end_time'   => sprintf('%02d:00:00', $hour + 1),
            'is_booked'  => false,
        ];
    }
}
