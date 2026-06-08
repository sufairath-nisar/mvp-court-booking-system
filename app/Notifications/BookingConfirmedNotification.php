<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the consumer when their booking is confirmed.
 *
 * Uses the `mail` channel; with the default `log` mailer the email is written
 * to storage/logs/laravel.log (no SMTP credentials required for the demo).
 */
class BookingConfirmedNotification extends Notification
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
            ->subject('Booking Confirmed - ' . ($booking->court->name ?? 'Court'))
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your court booking has been confirmed.')
            ->line('Court: ' . ($booking->court->name ?? 'N/A'))
            ->line('Date: ' . $booking->booking_date->format('Y-m-d'))
            ->line('Time: ' . substr((string) $booking->slot?->start_time, 0, 5) . ' - ' . substr((string) $booking->slot?->end_time, 0, 5))
            ->line('Thank you for booking with us!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'court_id'   => $this->booking->court_id,
            'status'     => 'booked',
        ];
    }
}
