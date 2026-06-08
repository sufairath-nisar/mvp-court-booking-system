<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\CourtSlot;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CourtSeeder extends Seeder
{
    /**
     * Seed a few demo courts, each with hourly slots for the next 3 days.
     */
    public function run(): void
    {
        $courts = [
            ['name' => 'Center Court',   'location' => 'Block A', 'sport_type' => 'tennis',   'hourly_rate' => 25.00],
            ['name' => 'Turf Arena 1',   'location' => 'Block B', 'sport_type' => 'football', 'hourly_rate' => 40.00],
            ['name' => 'Smash Court',    'location' => 'Block C', 'sport_type' => 'badminton','hourly_rate' => 15.00],
        ];

        foreach ($courts as $data) {
            $court = Court::create($data + ['is_active' => true]);

            // Generate 8 AM–4 PM hourly slots for the next 3 days.
            for ($day = 0; $day < 3; $day++) {
                $date = Carbon::today()->addDays($day)->format('Y-m-d');

                for ($hour = 8; $hour < 16; $hour++) {
                    CourtSlot::create([
                        'court_id'   => $court->id,
                        'date'       => $date,
                        'start_time' => sprintf('%02d:00:00', $hour),
                        'end_time'   => sprintf('%02d:00:00', $hour + 1),
                        'is_booked'  => false,
                    ]);
                }
            }
        }
    }
}
