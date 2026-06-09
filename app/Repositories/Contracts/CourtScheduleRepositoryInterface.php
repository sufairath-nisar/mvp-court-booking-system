<?php

namespace App\Repositories\Contracts;

use App\Models\Court;
use Illuminate\Database\Eloquent\Collection;

interface CourtScheduleRepositoryInterface
{
    /**
     * All weekly schedule rows for a court, ordered by day of week.
     */
    public function forCourt(int $courtId): Collection;

    /**
     * Replace a court's entire weekly schedule with the given rows.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function replaceForCourt(Court $court, array $rows): Collection;

    /**
     * Create or update a single weekday's schedule row (keyed by day_of_week),
     * leaving the court's other days untouched.
     *
     * @param array<string, mixed> $row
     */
    public function upsertDay(Court $court, array $row): \App\Models\CourtSchedule;
}
