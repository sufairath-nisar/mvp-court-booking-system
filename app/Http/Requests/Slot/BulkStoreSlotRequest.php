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
     * Generate slots for a list of admin-selected dates in one request.
     *
     * The admin picks specific dates (calendar on the front end); each date carries its
     * own start/end time, and some dates may share the same window. `slot_duration` is
     * the global default in minutes (60 = 1 hour); a date may override it.
     *
     * Overlap handling + the per-date slot stepping live in SlotService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'court_id'              => ['required', 'integer', 'exists:courts,id'],
            'slot_duration'         => ['sometimes', 'integer', 'min:15', 'max:480'],
            'dates'                 => ['required', 'array', 'min:1', 'max:120'],
            'dates.*.date'          => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'dates.*.start_time'    => ['required', 'date_format:H:i'],
            'dates.*.end_time'      => ['required', 'date_format:H:i'],
            'dates.*.slot_duration' => ['sometimes', 'integer', 'min:15', 'max:480'],
        ];
    }

    /**
     * Friendlier names for the nested array fields in validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'dates.*.date'       => 'date',
            'dates.*.start_time' => 'start time',
            'dates.*.end_time'   => 'end time',
        ];
    }
}
