<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class SetCourtScheduleRequest extends FormRequest
{
    /**
     * Role is already enforced by the `role:admin` middleware on the route group.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Replace a court's weekly schedule. One entry per day-of-week.
     *
     * open < close per row is enforced in ScheduleService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'schedule'                 => ['required', 'array', 'min:1', 'max:7'],
            'schedule.*.day_of_week'   => ['required', 'integer', 'between:0,6'], // 0=Sun..6=Sat
            'schedule.*.open_time'     => ['required', 'date_format:H:i'],
            'schedule.*.close_time'    => ['required', 'date_format:H:i'],
            'schedule.*.slot_duration' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'schedule.*.is_active'     => ['sometimes', 'boolean'],
        ];
    }
}
