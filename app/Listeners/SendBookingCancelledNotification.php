<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Notifications\BookingCancelledNotification;

class SendBookingCancelledNotification
{
    public function handle(BookingCancelled $event): void
    {
        $booking = $event->booking->loadMissing('user');

        $booking->user?->notify(new BookingCancelledNotification($booking));
    }
}
