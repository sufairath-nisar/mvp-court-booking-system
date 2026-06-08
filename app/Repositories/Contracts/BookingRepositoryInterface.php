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
