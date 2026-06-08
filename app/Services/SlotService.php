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
     * Bulk-generate slots for a court across a date range.
     *
     * Instead of an admin creating each slot by hand, this builds a whole schedule
     * in one call. Each day's hours come from one or more "schedules" (a window of
     * start/end + slot length applied to chosen days-of-week), so different days can
     * differ — e.g. Mon 09:00-21:00 while Fri runs 08:00-12:00. A slot that would
     * overlap an existing one is skipped, not errored.
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

        $start = Carbon::createFromFormat('Y-m-d', $data['start_date'])->startOfDay();
        $end   = Carbon::createFromFormat('Y-m-d', $data['end_date'])->startOfDay();

        if ($start->diffInDays($end) > 90) {
            throw new BusinessRuleException('The date range may not exceed 90 days.');
        }

        $schedules = $this->normaliseSchedules($data);

        $created = [];
        $skipped = 0;

        DB::transaction(function () use ($start, $end, $schedules, $courtId, &$created, &$skipped) {
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                foreach ($schedules as $schedule) {
                    if ($schedule['days'] !== null && ! in_array($date->dayOfWeek, $schedule['days'], true)) {
                        continue;
                    }

                    $this->generateDaySlots($courtId, $date, $schedule, $created, $skipped);
                }
            }
        });

        return [
            'created'       => $created,
            'created_count' => count($created),
            'skipped_count' => $skipped,
        ];
    }

    /**
     * Normalise the request into a uniform list of schedule windows.
     *
     * Accepts either the per-day `schedules` array or the flat single-window form;
     * both reduce to: [{ days: int[]|null, start: 'H:i', end: 'H:i', duration: int }].
     *
     * @param array<string, mixed> $data
     * @return array<int, array{days: array<int,int>|null, start: string, end: string, duration: int}>
     *
     * @throws BusinessRuleException
     */
    private function normaliseSchedules(array $data): array
    {
        if (! empty($data['schedules'])) {
            return array_map(function (array $schedule): array {
                if ($schedule['start_time'] >= $schedule['end_time']) {
                    throw new BusinessRuleException("Each schedule's start_time must be before its end_time.");
                }

                return [
                    'days'     => $schedule['days_of_week'] ?? null,
                    'start'    => $schedule['start_time'],
                    'end'      => $schedule['end_time'],
                    'duration' => (int) ($schedule['slot_duration'] ?? 60),
                ];
            }, $data['schedules']);
        }

        // Flat form: a single window applied to the selected (or all) days.
        return [[
            'days'     => $data['days_of_week'] ?? null,
            'start'    => $data['daily_start_time'],
            'end'      => $data['daily_end_time'],
            'duration' => (int) ($data['slot_duration'] ?? 60),
        ]];
    }

    /**
     * Step through one day's window and create each non-overlapping slot.
     *
     * @param array{days: array<int,int>|null, start: string, end: string, duration: int} $schedule
     * @param array<int, CourtSlot> $created
     */
    private function generateDaySlots(int $courtId, Carbon $date, array $schedule, array &$created, int &$skipped): void
    {
        $duration = $schedule['duration'];
        $dayStart = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $schedule['start']);
        $dayEnd   = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $schedule['end']);

        for ($slotStart = $dayStart->copy(); $slotStart->copy()->addMinutes($duration)->lte($dayEnd); $slotStart->addMinutes($duration)) {
            $startStr = $slotStart->format('H:i:s');
            $endStr   = $slotStart->copy()->addMinutes($duration)->format('H:i:s');

            if ($this->slots->hasOverlap($courtId, $date->format('Y-m-d'), $startStr, $endStr)) {
                $skipped++;

                continue;
            }

            $created[] = $this->slots->create([
                'court_id'   => $courtId,
                'date'       => $date->format('Y-m-d'),
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
