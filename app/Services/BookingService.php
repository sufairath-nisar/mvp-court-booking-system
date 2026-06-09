<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Events\BookingCreated;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\CourtSlot;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\CourtScheduleExceptionRepositoryInterface;
use App\Repositories\Contracts\CourtSlotRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
        private readonly CourtSlotRepositoryInterface $slots,
        private readonly CourtScheduleExceptionRepositoryInterface $exceptions,
    ) {
    }

    /**
     * Book a recurring slot for a specific date.
     *
     * The slot defines the court + weekday + time; the consumer chooses the date. We
     * validate the date matches the slot's weekday, isn't in the past, isn't closed/
     * outside an exception's hours, then prevent double-booking the same (slot, date).
     *
     * @throws BusinessRuleException
     */
    public function book(User $user, int $slotId, string $bookingDate): Booking
    {
        try {
            $slot = $this->slots->findOrFail($slotId);
        } catch (ModelNotFoundException) {
            throw new BusinessRuleException('The selected slot does not exist.', 404);
        }

        $date = Carbon::createFromFormat('Y-m-d', $bookingDate)->startOfDay();

        if ($date->dayOfWeek !== $slot->day_of_week) {
            throw new BusinessRuleException('This slot is not available on the selected date.');
        }

        if (! $slot->is_active) {
            throw new BusinessRuleException('This slot is no longer available.');
        }

        if ($this->slotHasStarted($slot, $bookingDate)) {
            throw new BusinessRuleException('This slot is in the past and can no longer be booked.');
        }

        $this->assertDateIsOpen($slot, $bookingDate);

        $booking = DB::transaction(function () use ($user, $slot, $bookingDate) {
            if ($this->bookings->activeOverlapsForCourtDate($slot->court_id, $bookingDate, $slot->start_time, $slot->end_time)) {
                throw new BusinessRuleException('This slot has already been booked for that date.');
            }

            return $this->bookings->create([
                'user_id'      => $user->id,
                'court_id'     => $slot->court_id,
                'slot_id'      => $slot->id,
                'booking_date' => $bookingDate,
                'status'       => BookingStatus::BOOKED,
            ]);
        });

        BookingCreated::dispatch($booking->load(['court', 'slot']));

        return $booking->load(['court', 'slot']);
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

            $slot = $booking->slot;

            if ($slot && $this->slotHasStarted($slot, $booking->booking_date->format('Y-m-d'))) {
                throw new BusinessRuleException('Bookings can only be cancelled before the slot start time.');
            }

            $this->bookings->update($booking, ['status' => BookingStatus::CANCELLED]);

            return $booking->refresh()->load(['court', 'slot']);
        });

        BookingCancelled::dispatch($booking);

        return $booking;
    }

    /**
     * Whether the slot's start datetime (on the given date) is now or in the past.
     */
    private function slotHasStarted(CourtSlot $slot, string $date): bool
    {
        return Carbon::parse($date . ' ' . $slot->start_time)->isPast();
    }

    /**
     * Reject the booking if a date exception closes the court or the slot falls outside
     * an override window for that date.
     *
     * @throws BusinessRuleException
     */
    private function assertDateIsOpen(CourtSlot $slot, string $date): void
    {
        $exception = $this->exceptions->findForCourtDate($slot->court_id, $date);

        if (! $exception) {
            return;
        }

        if ($exception->is_closed) {
            throw new BusinessRuleException('The court is closed on the selected date.');
        }

        if ($exception->open_time && $exception->close_time) {
            $withinWindow = $slot->start_time >= $exception->open_time
                && $slot->end_time <= $exception->close_time;

            if (! $withinWindow) {
                throw new BusinessRuleException('This slot is outside the court\'s opening hours for the selected date.');
            }
        }
    }
}
