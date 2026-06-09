<?php

namespace App\Repositories\Eloquent;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CourtSlot;
use App\Repositories\Contracts\CourtSlotRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CourtSlotRepository implements CourtSlotRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return CourtSlot::query()
            ->with('court')
            ->when(isset($filters['court_id']), fn ($q) => $q->where('court_id', $filters['court_id']))
            ->when(isset($filters['day_of_week']), fn ($q) => $q->where('day_of_week', $filters['day_of_week']))
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function availableForCourtOnDate(int $courtId, int $dayOfWeek, string $date): Collection
    {
        // The time ranges already taken on this date (each booking's slot window). A
        // booking may point at a now-deactivated slot whose hours overlap the current
        // grid, so we compare by TIME, not by slot id.
        $bookedRanges = Booking::query()
            ->where('bookings.court_id', $courtId)
            ->where('bookings.booking_date', $date)
            ->where('bookings.status', BookingStatus::BOOKED->value)
            ->join('court_slots', 'bookings.slot_id', '=', 'court_slots.id')
            ->get(['court_slots.start_time as start_time', 'court_slots.end_time as end_time']);

        $slots = CourtSlot::query()
            ->where('court_id', $courtId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        // Drop any active slot whose window overlaps a booked range (start < end && end > start).
        return $slots->reject(function (CourtSlot $slot) use ($bookedRanges): bool {
            foreach ($bookedRanges as $range) {
                if ($slot->start_time < $range->end_time && $slot->end_time > $range->start_time) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * {@inheritDoc}
     */
    public function allForCourt(int $courtId): Collection
    {
        return CourtSlot::query()
            ->where('court_id', $courtId)
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function hasActiveBookings(CourtSlot $slot): bool
    {
        return $slot->bookings()
            ->where('status', BookingStatus::BOOKED->value)
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function findOrFail(int $id): CourtSlot
    {
        return CourtSlot::findOrFail($id);
    }

    /**
     * {@inheritDoc}
     */
    public function hasOverlap(int $courtId, int $dayOfWeek, string $startTime, string $endTime, ?int $ignoreSlotId = null): bool
    {
        return CourtSlot::query()
            ->where('court_id', $courtId)
            ->where('day_of_week', $dayOfWeek)
            ->when($ignoreSlotId, fn ($q) => $q->whereKeyNot($ignoreSlotId))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function upsertRecurring(int $courtId, int $dayOfWeek, string $startTime, string $endTime): CourtSlot
    {
        return CourtSlot::updateOrCreate(
            ['court_id' => $courtId, 'day_of_week' => $dayOfWeek, 'start_time' => $startTime],
            ['end_time' => $endTime, 'is_active' => true],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): CourtSlot
    {
        return CourtSlot::create($data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(CourtSlot $slot, array $data): CourtSlot
    {
        $slot->update($data);

        return $slot->refresh();
    }

    /**
     * {@inheritDoc}
     */
    public function delete(CourtSlot $slot): void
    {
        $slot->delete();
    }
}
