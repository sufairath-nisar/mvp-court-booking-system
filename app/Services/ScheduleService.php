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

        return DB::transaction(fn () => $this->schedules->replaceForCourt($court, $rows));
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
     * Generate concrete bookable slots for a date range from the weekly schedule,
     * with date exceptions overriding it (closed days are skipped). Overlapping
     * slots are skipped, not errored.
     *
     * @return array{created_count: int, skipped_count: int}
     *
     * @throws BusinessRuleException
     */
    /**
     * Create the court's RECURRING slots by expanding its weekly schedule.
     *
     * Each active schedule row (day_of_week, open–close, duration) is sliced into
     * fixed-length recurring windows — e.g. Mon 09:00–12:00 ⇒ 3 slots that apply to
     * EVERY Monday. No dates are involved; the booked date lives on the booking.
     * Idempotent: re-running upserts (won't duplicate or disturb booked slots).
     *
     * @return array{created_count: int, existing_count: int}
     *
     * @throws BusinessRuleException
     */
    public function generateRecurringSlots(int $courtId): array
    {
        $this->courts->findOrFail($courtId);

        $schedules = $this->schedules->forCourt($courtId)->where('is_active', true);

        if ($schedules->isEmpty()) {
            throw new BusinessRuleException('Set the weekly schedule before generating slots.');
        }

        $created = 0;
        $existing = 0;

        DB::transaction(function () use ($schedules, $courtId, &$created, &$existing) {
            foreach ($schedules as $schedule) {
                $duration = $schedule->slot_duration;
                $cursor   = Carbon::parse((string) $schedule->open_time);
                $dayEnd   = Carbon::parse((string) $schedule->close_time);

                while ($cursor->copy()->addMinutes($duration)->lte($dayEnd)) {
                    $slot = $this->slots->upsertRecurring(
                        $courtId,
                        $schedule->day_of_week,
                        $cursor->format('H:i:s'),
                        $cursor->copy()->addMinutes($duration)->format('H:i:s'),
                    );

                    $slot->wasRecentlyCreated ? $created++ : $existing++;

                    $cursor->addMinutes($duration);
                }
            }
        });

        return ['created_count' => $created, 'existing_count' => $existing];
    }
}
