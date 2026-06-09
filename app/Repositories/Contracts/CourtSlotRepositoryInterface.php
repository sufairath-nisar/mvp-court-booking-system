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
     * Active recurring slots for a court on a given day-of-week whose time window does
     * NOT overlap any active booking on the given date.
     */
    public function availableForCourtOnDate(int $courtId, int $dayOfWeek, string $date): Collection;

    /**
     * All slots for a court (active and inactive) — used when reconciling to a schedule.
     */
    public function allForCourt(int $courtId): Collection;

    /**
     * Whether the slot has any active (booked) booking, on any date.
     */
    public function hasActiveBookings(CourtSlot $slot): bool;

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
