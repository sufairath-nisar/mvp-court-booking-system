<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\CourtSchedule;
use App\Models\CourtSlot;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CourtSeeder extends Seeder
{
    /**
     * Seed demo courts, each with a weekly schedule and its recurring slots.
     *
     * Slots are RECURRING (per day-of-week) — a consumer books one for a chosen date.
     */
    public function run(): void
    {
        $courts = [
            ['name' => 'Center Court', 'location' => 'Block A', 'sport_type' => 'tennis',    'hourly_rate' => 25.00],
            ['name' => 'Turf Arena 1', 'location' => 'Block B', 'sport_type' => 'football',  'hourly_rate' => 40.00],
            ['name' => 'Smash Court',  'location' => 'Block C', 'sport_type' => 'badminton', 'hourly_rate' => 15.00],
        ];

        // Mon–Thu 09:00–21:00, Fri 14:00–22:00, Sat 08:00–12:00, Sun closed.
        $schedule = [
            ['day_of_week' => 1, 'open_time' => '09:00:00', 'close_time' => '21:00:00'],
            ['day_of_week' => 2, 'open_time' => '09:00:00', 'close_time' => '21:00:00'],
            ['day_of_week' => 3, 'open_time' => '09:00:00', 'close_time' => '21:00:00'],
            ['day_of_week' => 4, 'open_time' => '09:00:00', 'close_time' => '21:00:00'],
            ['day_of_week' => 5, 'open_time' => '14:00:00', 'close_time' => '22:00:00'],
            ['day_of_week' => 6, 'open_time' => '08:00:00', 'close_time' => '12:00:00'],
        ];

        foreach ($courts as $data) {
            $court = Court::create($data + ['is_active' => true]);

            foreach ($schedule as $row) {
                CourtSchedule::create($row + ['court_id' => $court->id, 'slot_duration' => 60, 'is_active' => true]);

                // Expand the day's window into recurring 1-hour slots.
                $cursor = Carbon::parse($row['open_time']);
                $end = Carbon::parse($row['close_time']);

                while ($cursor->copy()->addHour()->lte($end)) {
                    CourtSlot::create([
                        'court_id'    => $court->id,
                        'day_of_week' => $row['day_of_week'],
                        'start_time'  => $cursor->format('H:i:s'),
                        'end_time'    => $cursor->copy()->addHour()->format('H:i:s'),
                    ]);
                    $cursor->addHour();
                }
            }
        }
    }
}
