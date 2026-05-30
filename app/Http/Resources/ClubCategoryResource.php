<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubCategoryResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'club_uuid' => $this->club_uuid,
            'name' => $this->name,
            'sort_order' => (int) $this->sort_order,
            'is_private' => (bool) $this->is_private,
            'channels' => ClubChannelResource::collection($this->whenLoaded('channels')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
