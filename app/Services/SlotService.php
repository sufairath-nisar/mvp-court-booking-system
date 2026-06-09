<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\CourtSlot;
use App\Repositories\Contracts\CourtRepositoryInterface;
use App\Repositories\Contracts\CourtScheduleExceptionRepositoryInterface;
use App\Repositories\Contracts\CourtSlotRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SlotService
{
    public function __construct(
        private readonly CourtSlotRepositoryInterface $slots,
        private readonly CourtRepositoryInterface $courts,
        private readonly CourtScheduleExceptionRepositoryInterface $exceptions,
    ) {
    }

    /**
     * List (recurring) slots with optional filters.
     *
     * @param array<string, mixed> $filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->slots->paginate($filters, $perPage);
    }

    /**
     * Available slots for a court on a specific date (consumer-facing view).
     *
     * Returns the court's recurring slots for that weekday that are not already booked
     * on the date, with any date exception applied (closed = none; override = only the
     * slots inside the override window).
     *
     * @return Collection<int, CourtSlot>
     */
    public function availableForCourt(int $courtId, ?string $date = null): Collection
    {
        $this->courts->findOrFail($courtId);

        $date = $date ?: Carbon::today()->format('Y-m-d');
        $day  = Carbon::parse($date);

        $exception = $this->exceptions->findForCourtDate($courtId, $date);

        if ($exception && $exception->is_closed) {
            return new Collection(); // court closed that date
        }

        $slots = $this->slots->availableForCourtOnDate($courtId, $day->dayOfWeek, $date);

        // An hour-override exception narrows availability to its window for that date.
        if ($exception && $exception->open_time && $exception->close_time) {
            $slots = $slots->filter(
                fn (CourtSlot $slot) => $slot->start_time >= $exception->open_time
                    && $slot->end_time <= $exception->close_time
            )->values();
        }

        return $slots;
    }

    /**
     * Update a recurring slot (guards against overlapping the same court/day-of-week).
     *
     * @param array<string, mixed> $data
     *
     * @throws BusinessRuleException
     */
    public function update(int $id, array $data): CourtSlot
    {
        $slot = $this->slots->findOrFail($id);

        $courtId   = (int) ($data['court_id'] ?? $slot->court_id);
        $dayOfWeek = (int) ($data['day_of_week'] ?? $slot->day_of_week);
        $startTime = $data['start_time'] ?? $slot->start_time;
        $endTime   = $data['end_time'] ?? $slot->end_time;

        if (isset($data['court_id'])) {
            $this->courts->findOrFail($courtId);
        }

        $this->assertNoOverlap($courtId, $dayOfWeek, $startTime, $endTime, $slot->id);

        return $this->slots->update($slot, $data);
    }

    /**
     * Delete a recurring slot.
     */
    public function delete(int $id): void
    {
        $slot = $this->slots->findOrFail($id);

        $this->slots->delete($slot);
    }

    /**
     * Guard: end must be after start, and the window must not overlap an existing slot
     * for the same court on the same day-of-week.
     *
     * @throws BusinessRuleException
     */
    private function assertNoOverlap(int $courtId, int $dayOfWeek, string $startTime, string $endTime, ?int $ignoreSlotId = null): void
    {
        if ($startTime >= $endTime) {
            throw new BusinessRuleException('The start time must be before the end time.');
        }

        if ($this->slots->hasOverlap($courtId, $dayOfWeek, $startTime, $endTime, $ignoreSlotId)) {
            throw new BusinessRuleException('This time slot overlaps an existing slot for the court on that day.');
        }
    }
}
