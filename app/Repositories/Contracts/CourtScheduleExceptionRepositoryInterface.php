<?php

namespace App\Repositories\Contracts;

use App\Models\CourtScheduleException;
use Illuminate\Database\Eloquent\Collection;

interface CourtScheduleExceptionRepositoryInterface
{
    /**
     * All exceptions for a court, most recent date first.
     */
    public function forCourt(int $courtId): Collection;

    /**
     * Exceptions for a court within an inclusive date range.
     */
    public function forCourtBetween(int $courtId, string $startDate, string $endDate): Collection;

    /**
     * Create (or update) an exception for a court/date.
     *
     * @param array<string, mixed> $data
     */
    public function upsert(array $data): CourtScheduleException;

    /**
     * Find one exception belonging to a court.
     */
    public function findForCourt(int $courtId, int $id): ?CourtScheduleException;

    public function delete(CourtScheduleException $exception): void;
}
