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
     * Availability / past-slot / double-booking checks live in BookingService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slot_id' => ['required', 'integer', 'exists:court_slots,id'],
        ];
    }
}
