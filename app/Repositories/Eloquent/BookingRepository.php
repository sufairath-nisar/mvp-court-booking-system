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
    public function activeOverlapsForCourtDate(int $courtId, string $date, string $startTime, string $endTime): bool
    {
        return Booking::query()
            ->where('bookings.court_id', $courtId)
            ->where('bookings.booking_date', $date)
            ->where('bookings.status', BookingStatus::BOOKED->value)
            ->join('court_slots', 'bookings.slot_id', '=', 'court_slots.id')
            ->where('court_slots.start_time', '<', $endTime)
            ->where('court_slots.end_time', '>', $startTime)
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
