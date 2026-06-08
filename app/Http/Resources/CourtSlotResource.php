<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtSlotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'court_id'   => $this->court_id,
            'date'       => $this->date->format('Y-m-d'),
            'start_time' => substr((string) $this->start_time, 0, 5),
            'end_time'   => substr((string) $this->end_time, 0, 5),
            'is_booked'  => $this->is_booked,
            'court'      => new CourtResource($this->whenLoaded('court')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
