<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\CourtSlot;
use App\Repositories\Contracts\CourtRepositoryInterface;
use App\Repositories\Contracts\CourtSlotRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
     * Bulk-generate slots for a list of admin-selected dates.
     *
     * The admin picks specific dates (from a calendar on the front end), each with its
     * own start/end time — some dates may share the same window, others differ. This
     * slices each date's window into `slot_duration`-minute blocks (default 60 = 1 hour)
     * and creates the slots in one call. A slot that would overlap an existing one is
     * skipped, not errored.
     *
     * Expected shape:
     *   court_id, slot_duration?, dates: [{ date, start_time, end_time, slot_duration? }]
     *
     * @param array<string, mixed> $data
     * @return array{created: array<int, CourtSlot>, created_count: int, skipped_count: int}
     *
     * @throws BusinessRuleException
     */
    public function generateBulk(array $data): array
    {
        $courtId = (int) $data['court_id'];
        $this->courts->findOrFail($courtId);

        $defaultDuration = (int) ($data['slot_duration'] ?? 60);

        $created = [];
        $skipped = 0;

        DB::transaction(function () use ($data, $courtId, $defaultDuration, &$created, &$skipped) {
            foreach ($data['dates'] as $entry) {
                if ($entry['start_time'] >= $entry['end_time']) {
                    throw new BusinessRuleException("start_time must be before end_time for {$entry['date']}.");
                }

                $duration = (int) ($entry['slot_duration'] ?? $defaultDuration);

                $this->generateDaySlots($courtId, $entry['date'], $entry['start_time'], $entry['end_time'], $duration, $created, $skipped);
            }
        });

        return [
            'created'       => $created,
            'created_count' => count($created),
            'skipped_count' => $skipped,
        ];
    }

    /**
     * Slice one date's [start, end] window into fixed-length slots and create them.
     *
     * @param array<int, CourtSlot> $created
     */
    private function generateDaySlots(int $courtId, string $date, string $start, string $end, int $duration, array &$created, int &$skipped): void
    {
        $dayStart = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $start);
        $dayEnd   = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $end);

        for ($slotStart = $dayStart->copy(); $slotStart->copy()->addMinutes($duration)->lte($dayEnd); $slotStart->addMinutes($duration)) {
            $startStr = $slotStart->format('H:i:s');
            $endStr   = $slotStart->copy()->addMinutes($duration)->format('H:i:s');

            if ($this->slots->hasOverlap($courtId, $date, $startStr, $endStr)) {
                $skipped++;

                continue;
            }

            $created[] = $this->slots->create([
                'court_id'   => $courtId,
                'date'       => $date,
                'start_time' => $startStr,
                'end_time'   => $endStr,
                'is_booked'  => false,
            ]);
        }
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
