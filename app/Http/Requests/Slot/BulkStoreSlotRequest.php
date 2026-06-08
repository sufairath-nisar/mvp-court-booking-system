<?php

namespace App\Http\Requests\Slot;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreSlotRequest extends FormRequest
{
    /**
     * Role is already enforced by the `role:admin` middleware on the route group.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Generate many slots at once for a court over a date range.
     *
     * Overlap handling + the actual slot stepping live in SlotService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'court_id'         => ['required', 'integer', 'exists:courts,id'],
            'start_date'       => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date'         => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'daily_start_time' => ['required', 'date_format:H:i'],
            'daily_end_time'   => ['required', 'date_format:H:i', 'after:daily_start_time'],
            'slot_duration'    => ['sometimes', 'integer', 'min:15', 'max:480'],
            'days_of_week'     => ['sometimes', 'array'],
            'days_of_week.*'   => ['integer', 'between:0,6'], // 0 = Sunday ... 6 = Saturday
        ];
    }
}
