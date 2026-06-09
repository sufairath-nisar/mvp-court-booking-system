<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleDayRequest extends FormRequest
{
    /**
     * Role is already enforced by the `role:admin` middleware on the route group.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Update a SINGLE weekday of a court's weekly schedule, leaving the other days
     * untouched. open < close is enforced in ScheduleService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'day_of_week'   => ['required', 'integer', 'between:0,6'], // 0=Sun..6=Sat
            'open_time'     => ['required', 'date_format:H:i'],
            'close_time'    => ['required', 'date_format:H:i'],
            'slot_duration' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'is_active'     => ['sometimes', 'boolean'],
        ];
    }
}
