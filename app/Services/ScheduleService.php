<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\CourtScheduleException;
use App\Repositories\Contracts\CourtRepositoryInterface;
use App\Repositories\Contracts\CourtScheduleExceptionRepositoryInterface;
use App\Repositories\Contracts\CourtScheduleRepositoryInterface;
use App\Repositories\Contracts\CourtSlotRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleService
{
    public function __construct(
        private readonly CourtRepositoryInterface $courts,
        private readonly CourtScheduleRepositoryInterface $schedules,
        private readonly CourtScheduleExceptionRepositoryInterface $exceptions,
        private readonly CourtSlotRepositoryInterface $slots,
    ) {
    }

    /**
     * The court's weekly schedule (one row per configured day-of-week).
     */
    public function getWeeklySchedule(int $courtId): Collection
    {
        $this->courts->findOrFail($courtId);

        return $this->schedules->forCourt($courtId);
    }

    /**
     * Replace a court's weekly schedule.
     *
     * @param array<int, array<string, mixed>> $schedule
     *
     * @throws BusinessRuleException
     */
    public function setWeeklySchedule(int $courtId, array $schedule): Collection
    {
        $court = $this->courts->findOrFail($courtId);

        $seenDays = [];
        $rows = [];

        foreach ($schedule as $entry) {
            $day = (int) $entry['day_of_week'];

            if (in_array($day, $seenDays, true)) {
                throw new BusinessRuleException("Duplicate schedule entry for day_of_week {$day}.");
            }
            if ($entry['open_time'] >= $entry['close_time']) {
                throw new BusinessRuleException("open_time must be before close_time for day_of_week {$day}.");
            }

            $seenDays[] = $day;
            $rows[] = [
                'day_of_week'   => $day,
                'open_time'     => $entry['open_time'],
                'close_time'    => $entry['close_time'],
                'slot_duration' => (int) ($entry['slot_duration'] ?? 60),
                'is_active'     => $entry['is_active'] ?? true,
            ];
        }

        return DB::transaction(function () use ($court, $rows) {
            $schedule = $this->schedules->replaceForCourt($court, $rows);

            // Saving the schedule re-shapes the court's recurring slots to the new hours.
            $this->reconcileSlots($court->id);

            return $schedule;
        });
    }

    /**
     * Update a SINGLE weekday of the court's schedule without disturbing the others,
     * then reconcile that court's recurring slots to the change.
     *
     * @param array<string, mixed> $data
     *
     * @throws BusinessRuleException
     */
    public function updateDaySchedule(int $courtId, array $data): Collection
    {
        $court = $this->courts->findOrFail($courtId);

        if ($data['open_time'] >= $data['close_time']) {
            throw new BusinessRuleException('open_time must be before close_time.');
        }

        return DB::transaction(function () use ($court, $data) {
            $this->schedules->upsertDay($court, [
                'day_of_week'   => (int) $data['day_of_week'],
                'open_time'     => $data['open_time'],
                'close_time'    => $data['close_time'],
                'slot_duration' => (int) ($data['slot_duration'] ?? 60),
                'is_active'     => $data['is_active'] ?? true,
            ]);

            $this->reconcileSlots($court->id);

            return $this->schedules->forCourt($court->id);
        });
    }

    /**
     * All date-specific exceptions for a court.
     */
    public function listExceptions(int $courtId): Collection
    {
        $this->courts->findOrFail($courtId);

        return $this->exceptions->forCourt($courtId);
    }

    /**
     * Add (or update) a date exception — close the court, or override its hours.
     *
     * @param array<string, mixed> $data
     *
     * @throws BusinessRuleException
     */
    public function addException(int $courtId, array $data): CourtScheduleException
    {
        $this->courts->findOrFail($courtId);

        $isClosed = (bool) ($data['is_closed'] ?? false);

        if (! $isClosed) {
            if (empty($data['open_time']) || empty($data['close_time'])) {
                throw new BusinessRuleException('open_time and close_time are required unless the court is closed that day.');
            }
            if ($data['open_time'] >= $data['close_time']) {
                throw new BusinessRuleException('open_time must be before close_time.');
            }
        }

        return $this->exceptions->upsert([
            'court_id'      => $courtId,
            'date'          => $data['date'],
            'is_closed'     => $isClosed,
            'open_time'     => $isClosed ? null : $data['open_time'],
            'close_time'    => $isClosed ? null : $data['close_time'],
            'slot_duration' => $data['slot_duration'] ?? null,
            'reason'        => $data['reason'] ?? null,
        ]);
    }

    /**
     * Delete a court's date exception.
     *
     * @throws BusinessRuleException
     */
    public function deleteException(int $courtId, int $exceptionId): void
    {
        $exception = $this->exceptions->findForCourt($courtId, $exceptionId);

        if (! $exception) {
            throw new BusinessRuleException('Schedule exception not found.', 404);
        }

        $this->exceptions->delete($exception);
    }

    /**
     * Create (or re-sync) the court's RECURRING slots from its weekly schedule.
     *
     * Each active schedule row (day_of_week, open–close, duration) is sliced into
     * fixed-length recurring windows — e.g. Mon 09:00–12:00 ⇒ 3 slots that apply to
     * EVERY Monday. No dates are involved; the booked date lives on the booking.
     * Idempotent: re-running won't duplicate. Delegates to reconcileSlots.
     *
     * @return array{created_count: int, existing_count: int, deactivated_count: int, deleted_count: int}
     *
     * @throws BusinessRuleException
     */
    public function generateRecurringSlots(int $courtId): array
    {
        $this->courts->findOrFail($courtId);

        if ($this->schedules->forCourt($courtId)->where('is_active', true)->isEmpty()) {
            throw new BusinessRuleException('Set the weekly schedule before generating slots.');
        }

        return DB::transaction(fn () => $this->reconcileSlots($courtId));
    }

    /**
     * Reconcile a court's recurring slots to its current weekly schedule.
     *
     * - A window in the new schedule that already exists is kept (and re-activated).
     * - A new window is created.
     * - A slot no longer in the schedule (stale) is DELETED if it has no active
     *   bookings, otherwise DEACTIVATED — never deleted, because the bookings table
     *   cascade-deletes on slot removal and we must not destroy a real reservation.
     *
     * Must run inside a transaction.
     *
     * @return array{created_count: int, existing_count: int, deactivated_count: int, deleted_count: int}
     */
    private function reconcileSlots(int $courtId): array
    {
        $schedules = $this->schedules->forCourt($courtId)->where('is_active', true);

        $created = $existing = $deactivated = $deleted = 0;

        // Build the desired set of windows, keyed by "day|HH:MM:SS".
        $desired = [];
        foreach ($schedules as $schedule) {
            $duration = $schedule->slot_duration;
            $cursor   = Carbon::parse((string) $schedule->open_time);
            $dayEnd   = Carbon::parse((string) $schedule->close_time);

            while ($cursor->copy()->addMinutes($duration)->lte($dayEnd)) {
                $start = $cursor->format('H:i:s');
                $end   = $cursor->copy()->addMinutes($duration)->format('H:i:s');
                $desired[$schedule->day_of_week . '|' . $start] = [
                    'day'   => $schedule->day_of_week,
                    'start' => $start,
                    'end'   => $end,
                ];
                $cursor->addMinutes($duration);
            }
        }

        // Create / re-activate every desired window.
        foreach ($desired as $window) {
            $slot = $this->slots->upsertRecurring($courtId, $window['day'], $window['start'], $window['end']);
            $slot->wasRecentlyCreated ? $created++ : $existing++;
        }

        // Handle slots that are no longer part of the schedule.
        foreach ($this->slots->allForCourt($courtId) as $slot) {
            $key = $slot->day_of_week . '|' . Carbon::parse((string) $slot->start_time)->format('H:i:s');

            if (isset($desired[$key])) {
                continue; // still in the schedule
            }

            if ($this->slots->hasActiveBookings($slot)) {
                $this->slots->update($slot, ['is_active' => false]); // keep the booking, hide the slot
                $deactivated++;
            } else {
                $this->slots->delete($slot);
                $deleted++;
            }
        }

        return [
            'created_count'     => $created,
            'existing_count'    => $existing,
            'deactivated_count' => $deactivated,
            'deleted_count'     => $deleted,
        ];
    }
}
