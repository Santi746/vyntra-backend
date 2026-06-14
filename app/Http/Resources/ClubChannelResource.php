<?php

namespace App\Http\Resources;

use App\Models\ClubChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear un Canal de Club en JSON.
 *
 * @property-read ClubChannel $resource
 */
class ClubChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => (string) $this->uuid,
            'category_uuid' => (string) $this->category_uuid,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'sort_order' => (int) $this->sort_order,
            'is_private' => (bool) $this->is_private,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
