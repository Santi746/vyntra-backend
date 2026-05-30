<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'os' => $this->os,
            'browser' => $this->browser,
            'ip' => $this->ip,
            'location' => $this->location,
            'is_current' => (bool) $this->is_current,
            'type' => $this->type,
        ];
    }
}
