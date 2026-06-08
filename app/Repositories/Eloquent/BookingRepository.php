<?php

namespace App\Repositories\Eloquent;

use App\Models\Booking;
use App\Repositories\Contracts\BookingRepositoryInterface;

class BookingRepository implements BookingRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findOrFail(int $id): Booking
    {
        return Booking::findOrFail($id);
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): Booking
    {
        return Booking::create($data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(Booking $booking, array $data): Booking
    {
        $booking->update($data);

        return $booking->refresh();
    }
}
