<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleExceptionRequest extends FormRequest
{
    /**
     * Role is already enforced by the `role:admin` middleware on the route group.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A date override: either close the court (`is_closed`) or set special hours.
     *
     * The "hours required unless closed" rule is enforced in ScheduleService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date'          => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'is_closed'     => ['sometimes', 'boolean'],
            'open_time'     => ['nullable', 'date_format:H:i'],
            'close_time'    => ['nullable', 'date_format:H:i'],
            'slot_duration' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'reason'        => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
