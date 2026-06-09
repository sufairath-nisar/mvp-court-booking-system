<?php

namespace App\Repositories\Eloquent;

use App\Enums\BookingStatus;
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
    public function activeExistsForSlotDate(int $slotId, string $date): bool
    {
        return Booking::query()
            ->where('slot_id', $slotId)
            ->where('booking_date', $date)
            ->where('status', BookingStatus::BOOKED->value)
            ->lockForUpdate()
            ->exists();
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
