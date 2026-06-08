<?php

namespace App\Repositories\Eloquent;

use App\Models\Court;
use App\Repositories\Contracts\CourtRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CourtRepository implements CourtRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Court::query()
            ->when(isset($filters['sport_type']), fn ($q) => $q->where('sport_type', $filters['sport_type']))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->when(isset($filters['search']), fn ($q) => $q->where(function ($sub) use ($filters) {
                $sub->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('location', 'like', "%{$filters['search']}%");
            }))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function findOrFail(int $id): Court
    {
        return Court::findOrFail($id);
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): Court
    {
        return Court::create($data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(Court $court, array $data): Court
    {
        $court->update($data);

        return $court->refresh();
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Court $court): void
    {
        $court->delete();
    }
}
