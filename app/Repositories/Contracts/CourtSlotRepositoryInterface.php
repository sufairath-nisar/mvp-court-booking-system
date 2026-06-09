<?php

namespace App\Repositories\Contracts;

use App\Models\CourtSlot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CourtSlotRepositoryInterface
{
    /**
     * Paginate slots, optionally filtered (court_id, day_of_week).
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Recurring slots for a court on a given day-of-week that are NOT already booked
     * on the given date (status = booked).
     */
    public function availableForCourtOnDate(int $courtId, int $dayOfWeek, string $date): Collection;

    /**
     * Find a slot by id or fail.
     */
    public function findOrFail(int $id): CourtSlot;

    /**
     * Whether a time window overlaps an existing slot for the court on the same day-of-week.
     */
    public function hasOverlap(int $courtId, int $dayOfWeek, string $startTime, string $endTime, ?int $ignoreSlotId = null): bool;

    /**
     * Create (or fetch existing) a recurring slot, keyed by court + day + start time.
     */
    public function upsertRecurring(int $courtId, int $dayOfWeek, string $startTime, string $endTime): CourtSlot;

    /**
     * Create a new slot.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): CourtSlot;

    /**
     * Update an existing slot.
     *
     * @param array<string, mixed> $data
     */
    public function update(CourtSlot $slot, array $data): CourtSlot;

    /**
     * Delete a slot.
     */
    public function delete(CourtSlot $slot): void;
}
