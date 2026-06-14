<?php

namespace App\Http\Resources;

use App\Models\ClubCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear una Categoría de Club en JSON.
 *
 * @property-read ClubCategory $resource
 */
class ClubCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => (string) $this->uuid,
            'club_uuid' => (string) $this->club_uuid,
            'name' => $this->name,
            'sort_order' => (int) $this->sort_order,
            'is_private' => (bool) $this->is_private,
            'channels' => ClubChannelResource::collection($this->whenLoaded('channels')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
