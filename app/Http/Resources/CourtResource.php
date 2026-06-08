<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CourtResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'location'    => $this->location,
            'sport_type'  => $this->sport_type,
            'hourly_rate' => (float) $this->hourly_rate,
            'is_active'   => $this->is_active,
            'image_url'   => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'slots'       => CourtSlotResource::collection($this->whenLoaded('slots')),
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
