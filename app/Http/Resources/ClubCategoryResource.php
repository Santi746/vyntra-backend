<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear una Categoría de Club en JSON.
 *
 * Incluye los canales de la categoría si la relación fue cargada.
 *
 * @package App\Http\Resources
 *
 * @property-read \App\Models\ClubCategory $resource
 */
class ClubCategoryResource extends JsonResource
{
    /**
     * Transforma la categoría en un array JSON para la API.
     *
     * @param Request $request Petición HTTP
     * @return array<string, mixed> Datos de la categoría con sus canales
     */
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
