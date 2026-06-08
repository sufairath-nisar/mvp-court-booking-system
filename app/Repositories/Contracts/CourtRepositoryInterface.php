<?php

namespace App\Repositories\Contracts;

use App\Models\Court;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CourtRepositoryInterface
{
    /**
     * Paginate courts, optionally filtered.
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Find a court by id or fail.
     */
    public function findOrFail(int $id): Court;

    /**
     * Create a new court.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Court;

    /**
     * Update an existing court.
     *
     * @param array<string, mixed> $data
     */
    public function update(Court $court, array $data): Court;

    /**
     * Delete a court.
     */
    public function delete(Court $court): void;
}
