<?php

namespace App\Repositories\Eloquent;

use App\Models\Court;
use App\Models\CourtSchedule;
use App\Repositories\Contracts\CourtScheduleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CourtScheduleRepository implements CourtScheduleRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function forCourt(int $courtId): Collection
    {
        return CourtSchedule::query()
            ->where('court_id', $courtId)
            ->orderBy('day_of_week')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function replaceForCourt(Court $court, array $rows): Collection
    {
        $court->schedules()->delete();
        $court->schedules()->createMany($rows);

        return $this->forCourt($court->id);
    }
}
