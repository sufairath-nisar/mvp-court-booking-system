<?php

namespace App\Repositories\Eloquent;

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
            ->when(isset($filters['date']), fn ($q) => $q->whereDate('date', $filters['date']))
            ->when(isset($filters['is_booked']), fn ($q) => $q->where('is_booked', filter_var($filters['is_booked'], FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function availableForCourt(int $courtId, ?string $date = null): Collection
    {
        return CourtSlot::query()
            ->where('court_id', $courtId)
            ->available()
            ->when($date, fn ($q) => $q->whereDate('date', $date))
            ->whereRaw("TIMESTAMP(date, start_time) > NOW()")
            ->orderBy('date')
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
    public function findForUpdate(int $id): ?CourtSlot
    {
        return CourtSlot::whereKey($id)->lockForUpdate()->first();
    }

    /**
     * {@inheritDoc}
     */
    public function hasOverlap(int $courtId, string $date, string $startTime, string $endTime, ?int $ignoreSlotId = null): bool
    {
        return CourtSlot::query()
            ->where('court_id', $courtId)
            ->whereDate('date', $date)
            ->when($ignoreSlotId, fn ($q) => $q->whereKeyNot($ignoreSlotId))
            // Two intervals overlap when existing.start < new.end AND existing.end > new.start.
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();
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
