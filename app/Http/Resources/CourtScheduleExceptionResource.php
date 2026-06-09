<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtScheduleExceptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'court_id'      => $this->court_id,
            'date'          => $this->date->format('Y-m-d'),
            'is_closed'     => $this->is_closed,
            'open_time'     => $this->open_time ? substr((string) $this->open_time, 0, 5) : null,
            'close_time'    => $this->close_time ? substr((string) $this->close_time, 0, 5) : null,
            'slot_duration' => $this->slot_duration,
            'reason'        => $this->reason,
        ];
    }
}
