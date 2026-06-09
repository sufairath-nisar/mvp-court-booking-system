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
     * Create slots for a court by generating them from its weekly schedule
     * (+ date exceptions) across a date range.
     *
     * `start_date`/`end_date` are optional — omit them to use a rolling horizon
     * (today → +`days`, or the configured default). Pass `exclude_dates` for one-off
     * holidays, or `preview: true` to get the counts without saving.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'court_id'        => ['required', 'integer', 'exists:courts,id'],
            'start_date'      => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date'        => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'days'            => ['sometimes', 'integer', 'min:1', 'max:90'],
            'exclude_dates'   => ['sometimes', 'array'],
            'exclude_dates.*' => ['date_format:Y-m-d'],
            'preview'         => ['sometimes', 'boolean'],
        ];
    }
}
