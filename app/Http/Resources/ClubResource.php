<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "uuid" => $this->uuid,
            "name" => $this->name,
            "description" => $this->description,
            "category_tag" => $this->category_tag,
            "logo_url" => $this->logo_url,
            "banner_url" => $this->banner_url,
            "is_verified" => (bool) $this->is_verified,
            "members_count" => $this->whenHas('members_count'),
            "online_count" => $this->whenHas('online_count'),
            "created_at" => $this->created_at->toIso8601String(),
            "updated_at" => $this->updated_at->toIso8601String(),
            "owner_uuid" => $this->owner_uuid,
            "owner" => new UserResource($this->whenLoaded('clubOwner')),
        ];
    }
}
