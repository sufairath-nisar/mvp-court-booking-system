<?php

namespace App\Services;

use App\Models\Court;
use App\Repositories\Contracts\CourtRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CourtService
{
    public function __construct(
        private readonly CourtRepositoryInterface $courts,
    ) {
    }

    /**
     * List courts with optional filters.
     *
     * @param array<string, mixed> $filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->courts->paginate($filters, $perPage);
    }

    /**
     * Retrieve a single court.
     */
    public function find(int $id): Court
    {
        return $this->courts->findOrFail($id);
    }

    /**
     * Create a court.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Court
    {
        return $this->courts->create($data);
    }

    /**
     * Update a court.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): Court
    {
        $court = $this->courts->findOrFail($id);

        return $this->courts->update($court, $data);
    }

    /**
     * Delete a court.
     */
    public function delete(int $id): void
    {
        $court = $this->courts->findOrFail($id);

        $this->courts->delete($court);
    }
}
