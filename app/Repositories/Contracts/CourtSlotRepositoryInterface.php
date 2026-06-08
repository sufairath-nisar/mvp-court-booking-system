<?php

namespace App\Repositories\Contracts;

use App\Models\CourtSlot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CourtSlotRepositoryInterface
{
    /**
     * Paginate slots, optionally filtered.
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Available (un-booked) slots for a given court.
     */
    public function availableForCourt(int $courtId, ?string $date = null): Collection;

    /**
     * Find a slot by id or fail.
     */
    public function findOrFail(int $id): CourtSlot;

    /**
     * Lock a slot row for update inside a transaction (prevents race conditions).
     */
    public function findForUpdate(int $id): ?CourtSlot;

    /**
     * Determine whether a time window overlaps an existing slot for the court.
     */
    public function hasOverlap(int $courtId, string $date, string $startTime, string $endTime, ?int $ignoreSlotId = null): bool;

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
