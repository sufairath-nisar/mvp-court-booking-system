<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Events\BookingCreated;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\CourtSlotRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
        private readonly CourtSlotRepositoryInterface $slots,
    ) {
    }

    /**
     * Book a slot for the given consumer.
     *
     * Concurrency-safe: the slot row is locked FOR UPDATE inside a transaction so two
     * simultaneous requests cannot both pass the availability check. The DB-level unique
     * index on bookings is the final backstop against double booking.
     *
     * @throws BusinessRuleException
     */
    public function book(User $user, int $slotId): Booking
    {
        $booking = DB::transaction(function () use ($user, $slotId) {
            $slot = $this->slots->findForUpdate($slotId);

            if (! $slot) {
                throw new BusinessRuleException('The selected slot does not exist.', 404);
            }

            if ($slot->is_booked) {
                throw new BusinessRuleException('This slot has already been booked.');
            }

            if ($this->slotHasStarted($slot)) {
                throw new BusinessRuleException('This slot is in the past and can no longer be booked.');
            }

            $booking = $this->bookings->create([
                'user_id'      => $user->id,
                'court_id'     => $slot->court_id,
                'slot_id'      => $slot->id,
                'booking_date' => $slot->date->format('Y-m-d'),
                'status'       => BookingStatus::BOOKED,
            ]);

            $this->slots->update($slot, ['is_booked' => true]);

            return $booking->load(['court', 'slot']);
        });

        // Notify the consumer (mail) after the booking is durably committed.
        BookingCreated::dispatch($booking);

        return $booking;
    }

    /**
     * Cancel a booking. Only the owner may cancel, and only before the slot starts.
     *
     * @throws BusinessRuleException
     */
    public function cancel(User $user, int $bookingId): Booking
    {
        $booking = DB::transaction(function () use ($user, $bookingId) {
            try {
                $booking = $this->bookings->findOrFail($bookingId);
            } catch (ModelNotFoundException) {
                throw new BusinessRuleException('Booking not found.', 404);
            }

            if ($booking->user_id !== $user->id) {
                throw new BusinessRuleException('You are not authorised to cancel this booking.', 403);
            }

            if ($booking->status === BookingStatus::CANCELLED) {
                throw new BusinessRuleException('This booking has already been cancelled.');
            }

            $slot = $this->slots->findForUpdate($booking->slot_id);

            if ($slot && $this->slotHasStarted($slot)) {
                throw new BusinessRuleException('Bookings can only be cancelled before the slot start time.');
            }

            $this->bookings->update($booking, ['status' => BookingStatus::CANCELLED]);

            if ($slot) {
                $this->slots->update($slot, ['is_booked' => false]);
            }

            return $booking->refresh()->load(['court', 'slot']);
        });

        // Notify the consumer (mail) after the cancellation is durably committed.
        BookingCancelled::dispatch($booking);

        return $booking;
    }

    /**
     * Whether the slot's start datetime is now or in the past.
     */
    private function slotHasStarted(\App\Models\CourtSlot $slot): bool
    {
        $startsAt = Carbon::parse($slot->date->format('Y-m-d') . ' ' . $slot->start_time);

        return $startsAt->isPast();
    }
}
