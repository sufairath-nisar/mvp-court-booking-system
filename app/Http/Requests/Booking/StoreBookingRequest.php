<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * A slot is a recurring weekly window, so the consumer also picks the date.
     * Weekday-match / past / closed / double-booking checks live in BookingService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slot_id'      => ['required', 'integer', 'exists:court_slots,id'],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }
}
