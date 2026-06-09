<?php

namespace App\Http\Requests\Slot;

use Illuminate\Foundation\Http\FormRequest;

class StoreSlotRequest extends FormRequest
{
    /**
     * Role is already enforced by the `role:admin` middleware on the route group.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Create slots for a court = generate its recurring weekly slots from the schedule.
     * No dates are needed — slots recur every week; the booked date lives on the booking.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'court_id' => ['required', 'integer', 'exists:courts,id'],
        ];
    }
}
