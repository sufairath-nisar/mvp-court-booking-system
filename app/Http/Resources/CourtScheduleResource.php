<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtScheduleResource extends JsonResource
{
    private const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'court_id'      => $this->court_id,
            'day_of_week'   => $this->day_of_week,
            'day_name'      => self::DAY_NAMES[$this->day_of_week] ?? null,
            'open_time'     => substr((string) $this->open_time, 0, 5),
            'close_time'    => substr((string) $this->close_time, 0, 5),
            'slot_duration' => $this->slot_duration,
            'is_active'     => $this->is_active,
        ];
    }
}
