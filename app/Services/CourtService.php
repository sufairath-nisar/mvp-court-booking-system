<?php

namespace App\Services;

use App\Models\Court;
use App\Repositories\Contracts\CourtRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Store (or replace) a court's image on the public disk and persist its path.
     */
    public function uploadImage(int $id, UploadedFile $file): Court
    {
        $court = $this->courts->findOrFail($id);

        // Remove the previous image, if any, to avoid orphaned files.
        if ($court->image_path) {
            Storage::disk('public')->delete($court->image_path);
        }

        $path = $file->store('courts', 'public');

        return $this->courts->update($court, ['image_path' => $path]);
    }
}
