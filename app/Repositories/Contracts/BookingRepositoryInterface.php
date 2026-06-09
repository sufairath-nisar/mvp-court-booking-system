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
     * Whether an active (booked) booking already exists for this slot on this date.
     * Locks the matching rows FOR UPDATE — must be called inside a transaction.
     */
    public function activeExistsForSlotDate(int $slotId, string $date): bool;

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
