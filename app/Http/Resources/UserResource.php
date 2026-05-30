<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            "uuid" => $this->uuid,
            "username" => $this->username,
            "user_tag" => $this->user_tag,
            "first_name" => $this->first_name,
            "last_name" => $this->last_name,
            "avatar_url" => $this->avatar_url,
            "banner_url" => $this->banner_url,
            "bio" => $this->bio,
            "location" => $this->location,
            "is_online" => (bool)$this->is_online,
            "created_at" => $this->created_at->toIso8601String(),
            "updated_at" => $this->updated_at->toIso8601String(),
            "club_uuids" => $this->whenLoaded('memberships', function () {
                return $this->memberships->pluck('club_uuid'); // club_uuids es unico del frontend
            }),
        ];
    }
}
