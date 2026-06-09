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
    public function generateSlots(int $courtId, string $startDate, string $endDate): array
    {
        $this->courts->findOrFail($courtId);

        $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
        $end   = Carbon::createFromFormat('Y-m-d', $endDate)->startOfDay();

        if ($start->diffInDays($end) > 90) {
            throw new BusinessRuleException('The date range may not exceed 90 days.');
        }

        $templates  = $this->schedules->forCourt($courtId)->keyBy('day_of_week');
        $exceptions = $this->exceptions->forCourtBetween($courtId, $startDate, $endDate)
            ->keyBy(fn (CourtScheduleException $e) => $e->date->format('Y-m-d'));

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($start, $end, $templates, $exceptions, $courtId, &$created, &$skipped) {
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $window = $this->resolveWindow($date, $templates, $exceptions);

                if ($window === null) {
                    continue; // closed that day (no template, inactive, or an explicit closure)
                }

                $this->sliceDay($courtId, $date->format('Y-m-d'), $window['open'], $window['close'], $window['duration'], $created, $skipped);
            }
        });

        return ['created_count' => $created, 'skipped_count' => $skipped];
    }

    /**
     * Resolve the effective open/close/duration for one date: an exception (if any)
     * overrides the weekly template; returns null when the court is closed that day.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\CourtSchedule>          $templates
     * @param  \Illuminate\Support\Collection<string, CourtScheduleException>          $exceptions
     * @return array{open: string, close: string, duration: int}|null
     */
    private function resolveWindow(Carbon $date, $templates, $exceptions): ?array
    {
        $template = $templates->get($date->dayOfWeek);
        $exception = $exceptions->get($date->format('Y-m-d'));

        if ($exception) {
            if ($exception->is_closed) {
                return null;
            }

            $open  = $exception->open_time ?? $template?->open_time;
            $close = $exception->close_time ?? $template?->close_time;

            if (! $open || ! $close) {
                return null;
            }

            return [
                'open'     => $open,
                'close'    => $close,
                'duration' => (int) ($exception->slot_duration ?? $template?->slot_duration ?? 60),
            ];
        }

        if (! $template || ! $template->is_active) {
            return null;
        }

        return [
            'open'     => $template->open_time,
            'close'    => $template->close_time,
            'duration' => $template->slot_duration,
        ];
    }

    /**
     * Slice one date's [open, close] window into fixed-length slots and create them.
     */
    private function sliceDay(int $courtId, string $date, string $open, string $close, int $duration, int &$created, int &$skipped): void
    {
        $cursor  = Carbon::parse("{$date} {$open}");
        $dayEnd  = Carbon::parse("{$date} {$close}");

        while ($cursor->copy()->addMinutes($duration)->lte($dayEnd)) {
            $startStr = $cursor->format('H:i:s');
            $endStr   = $cursor->copy()->addMinutes($duration)->format('H:i:s');

            if ($this->slots->hasOverlap($courtId, $date, $startStr, $endStr)) {
                $skipped++;
            } else {
                $this->slots->create([
                    'court_id'   => $courtId,
                    'date'       => $date,
                    'start_time' => $startStr,
                    'end_time'   => $endStr,
                    'is_booked'  => false,
                ]);
                $created++;
            }

            $cursor->addMinutes($duration);
        }
    }
}
