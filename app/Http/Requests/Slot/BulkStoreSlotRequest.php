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
     * Two ways to specify the daily hours:
     *  1. Flat form  — `daily_start_time` / `daily_end_time` (+ optional `days_of_week`)
     *     applies the SAME hours to every (selected) day.
     *  2. `schedules` — an array of per-day-of-week windows, so different days can have
     *     different hours/durations (e.g. Mon 09:00-21:00, Fri 08:00-12:00).
     *
     * When `schedules` is present it takes precedence. Overlap handling + the actual
     * slot stepping live in SlotService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'court_id'   => ['required', 'integer', 'exists:courts,id'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date'   => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],

            // Flat form (used when `schedules` is absent — same hours for every day).
            'daily_start_time' => ['required_without:schedules', 'date_format:H:i'],
            'daily_end_time'   => ['required_without:schedules', 'date_format:H:i', 'after:daily_start_time'],
            'slot_duration'    => ['sometimes', 'integer', 'min:15', 'max:480'],
            'days_of_week'     => ['sometimes', 'array'],
            'days_of_week.*'   => ['integer', 'between:0,6'], // 0 = Sunday ... 6 = Saturday

            // Per-day form (overrides the flat form when present).
            'schedules'                  => ['sometimes', 'array', 'min:1'],
            'schedules.*.days_of_week'   => ['sometimes', 'array'],
            'schedules.*.days_of_week.*' => ['integer', 'between:0,6'],
            'schedules.*.start_time'     => ['required', 'date_format:H:i'],
            'schedules.*.end_time'       => ['required', 'date_format:H:i'],
            'schedules.*.slot_duration'  => ['sometimes', 'integer', 'min:15', 'max:480'],
        ];
    }
}
