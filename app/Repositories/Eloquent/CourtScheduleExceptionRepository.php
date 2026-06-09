<?php

namespace App\Repositories\Eloquent;

use App\Models\CourtScheduleException;
use App\Repositories\Contracts\CourtScheduleExceptionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CourtScheduleExceptionRepository implements CourtScheduleExceptionRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function forCourt(int $courtId): Collection
    {
        return CourtScheduleException::query()
            ->where('court_id', $courtId)
            ->orderByDesc('date')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function forCourtBetween(int $courtId, string $startDate, string $endDate): Collection
    {
        return CourtScheduleException::query()
            ->where('court_id', $courtId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function upsert(array $data): CourtScheduleException
    {
        return CourtScheduleException::updateOrCreate(
            ['court_id' => $data['court_id'], 'date' => $data['date']],
            $data
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findForCourt(int $courtId, int $id): ?CourtScheduleException
    {
        return CourtScheduleException::query()
            ->where('court_id', $courtId)
            ->whereKey($id)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function delete(CourtScheduleException $exception): void
    {
        $exception->delete();
    }
}
