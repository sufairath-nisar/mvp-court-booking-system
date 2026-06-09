<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtSlotResource extends JsonResource
{
    private const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /**
     * Transform the resource into an array.
     *
     * A slot is a recurring weekly window — it has a day_of_week, not a date.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'court_id'    => $this->court_id,
            'day_of_week' => $this->day_of_week,
            'day_name'    => self::DAY_NAMES[$this->day_of_week] ?? null,
            'start_time'  => substr((string) $this->start_time, 0, 5),
            'end_time'    => substr((string) $this->end_time, 0, 5),
            'is_active'   => (bool) $this->is_active,
            'court'       => new CourtResource($this->whenLoaded('court')),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
