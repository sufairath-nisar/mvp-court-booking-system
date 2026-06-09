<?php

namespace App\Repositories\Contracts;

use App\Models\Booking;

interface BookingRepositoryInterface
{
    /**
     * Find a booking by id or fail.
     */
    public function findOrFail(int $id): Booking;

    /**
     * Whether an active (booked) booking on this court+date occupies a time window that
     * overlaps the given [startTime, endTime). Compares by time so a booking on a now-
     * deactivated slot still blocks an overlapping current slot.
     *
     * Locks the matching rows FOR UPDATE — must be called inside a transaction.
     */
    public function activeOverlapsForCourtDate(int $courtId, string $date, string $startTime, string $endTime): bool;

    /**
     * Create a new booking.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Booking;

    /**
     * Update an existing booking.
     *
     * @param array<string, mixed> $data
     */
    public function update(Booking $booking, array $data): Booking;
}
