<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Notifications\BookingConfirmedNotification;

class SendBookingCreatedNotification
{
    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking->loadMissing('user');

        $booking->user?->notify(new BookingConfirmedNotification($booking));
    }
}
