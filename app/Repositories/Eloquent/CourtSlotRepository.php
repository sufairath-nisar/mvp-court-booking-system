<?php

namespace App\Repositories\Eloquent;

use App\Enums\BookingStatus;
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
        return CourtSlot::query()
            ->where('court_id', $courtId)
            ->where('day_of_week', $dayOfWeek)
            // Exclude slots that already have an active booking on this date.
            ->whereDoesntHave('bookings', function ($q) use ($date) {
                $q->where('booking_date', $date)
                    ->where('status', BookingStatus::BOOKED->value);
            })
            ->orderBy('start_time')
            ->get();
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
            ['end_time' => $endTime],
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
