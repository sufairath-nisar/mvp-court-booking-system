<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSlotsRequest extends FormRequest
{
    /**
     * Role is already enforced by the `role:admin` middleware on the route group.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Generate concrete slots from the court's weekly schedule (+ exceptions)
     * across an inclusive date range.
     *
     * `start_date`/`end_date` are optional: omit them and it defaults to a rolling
     * horizon (today → +30 days). Pass `exclude_dates` for one-off holidays.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_date'      => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date'        => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'days'            => ['sometimes', 'integer', 'min:1', 'max:90'], // horizon override when end_date omitted
            'exclude_dates'   => ['sometimes', 'array'],
            'exclude_dates.*' => ['date_format:Y-m-d'],
            'preview'         => ['sometimes', 'boolean'], // true => return counts without saving
        ];
    }
}
