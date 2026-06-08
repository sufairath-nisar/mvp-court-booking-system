<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'court_id'     => $this->court_id,
            'slot_id'      => $this->slot_id,
            'booking_date' => $this->booking_date->format('Y-m-d'),
            'status'       => $this->status->value,
            'court'        => new CourtResource($this->whenLoaded('court')),
            'slot'         => new CourtSlotResource($this->whenLoaded('slot')),
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
        ];
    }
}
