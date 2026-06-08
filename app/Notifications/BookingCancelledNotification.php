<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the consumer when their booking is cancelled.
 */
class BookingCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Booking $booking,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['court', 'slot']);

        return (new MailMessage)
            ->subject('Booking Cancelled - ' . ($booking->court->name ?? 'Court'))
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your court booking has been cancelled.')
            ->line('Court: ' . ($booking->court->name ?? 'N/A'))
            ->line('Date: ' . $booking->booking_date->format('Y-m-d'))
            ->line('The slot is now available for others to book.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'court_id'   => $this->booking->court_id,
            'status'     => 'cancelled',
        ];
    }
}
