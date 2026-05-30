<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'club_uuid' => $this->club_uuid,
            'name' => $this->name,
            'color' => $this->color,
            'permissions' => [
                'manage_channels' => (bool) $this->manage_channels,
                'manage_roles' => (bool) $this->manage_roles,
                'manage_members' => (bool) $this->manage_members,
                'send_messages' => (bool) $this->send_messages,
                'manage_club' => (bool) $this->manage_club,
            ],
            'is_fixed' => (bool) $this->is_fixed,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
