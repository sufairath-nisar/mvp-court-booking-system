<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\CourtSlot;
use App\Repositories\Contracts\CourtRepositoryInterface;
use App\Repositories\Contracts\CourtSlotRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SlotService
{
    public function __construct(
        private readonly CourtSlotRepositoryInterface $slots,
        private readonly CourtRepositoryInterface $courts,
    ) {
    }

    /**
     * List slots with optional filters.
     *
     * @param array<string, mixed> $filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->slots->paginate($filters, $perPage);
    }

    /**
     * Available slots for a court (consumer-facing view).
     */
    public function availableForCourt(int $courtId, ?string $date = null): Collection
    {
        // Ensures the court exists (throws ModelNotFoundException -> 404 otherwise).
        $this->courts->findOrFail($courtId);

        return $this->slots->availableForCourt($courtId, $date);
    }

    /**
     * Create a slot for a court, guarding against overlaps.
     *
     * @param array<string, mixed> $data
     *
     * @throws BusinessRuleException
     */
    public function create(array $data): CourtSlot
    {
        // Validate the court exists.
        $this->courts->findOrFail($data['court_id']);

        $this->assertNoOverlap(
            (int) $data['court_id'],
            $data['date'],
            $data['start_time'],
            $data['end_time'],
        );

        return $this->slots->create([
            'court_id'   => $data['court_id'],
            'date'       => $data['date'],
            'start_time' => $data['start_time'],
            'end_time'   => $data['end_time'],
            'is_booked'  => false,
        ]);
    }

    /**
     * Update a slot, guarding against overlaps and booked-slot mutation.
     *
     * @param array<string, mixed> $data
     *
     * @throws BusinessRuleException
     */
    public function update(int $id, array $data): CourtSlot
    {
        $slot = $this->slots->findOrFail($id);

        if ($slot->is_booked) {
            throw new BusinessRuleException('A booked slot cannot be modified.');
        }

        $courtId   = (int) ($data['court_id'] ?? $slot->court_id);
        $date      = $data['date'] ?? $slot->date->format('Y-m-d');
        $startTime = $data['start_time'] ?? $slot->start_time;
        $endTime   = $data['end_time'] ?? $slot->end_time;

        if (isset($data['court_id'])) {
            $this->courts->findOrFail($courtId);
        }

        $this->assertNoOverlap($courtId, $date, $startTime, $endTime, $slot->id);

        return $this->slots->update($slot, $data);
    }

    /**
     * Delete a slot. Booked slots may not be deleted.
     *
     * @throws BusinessRuleException
     */
    public function delete(int $id): void
    {
        $slot = $this->slots->findOrFail($id);

        if ($slot->is_booked) {
            throw new BusinessRuleException('A booked slot cannot be deleted.');
        }

        $this->slots->delete($slot);
    }

    /**
     * Guard: the time window must not overlap an existing slot, and end must be after start.
     *
     * @throws BusinessRuleException
     */
    private function assertNoOverlap(int $courtId, string $date, string $startTime, string $endTime, ?int $ignoreSlotId = null): void
    {
        if ($startTime >= $endTime) {
            throw new BusinessRuleException('The start time must be before the end time.');
        }

        if ($this->slots->hasOverlap($courtId, $date, $startTime, $endTime, $ignoreSlotId)) {
            throw new BusinessRuleException('This time slot overlaps an existing slot for the court.');
        }
    }
}
