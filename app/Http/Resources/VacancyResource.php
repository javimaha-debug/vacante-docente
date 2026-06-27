<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VacancyResource extends JsonResource
{
    /**
     * Optional map of distances keyed by mode, injected by the controller:
     * ['driving' => ['duration_minutes' => .., 'distance_km' => .., 'traffic_note' => ..], ...]
     *
     * @var array<string, mixed>|null
     */
    public ?array $distances = null;

    public function withDistances(?array $distances): static
    {
        $this->distances = $distances;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'specialty_id' => $this->specialty_id,
            'num' => $this->num,
            'provincia' => $this->provincia,
            'localidad' => $this->localidad,
            'centro_codigo' => $this->centro_codigo,
            'centro_nombre' => $this->centro_nombre,
            'tipo_centro' => $this->tipo_centro,
            'lloc' => $this->lloc,
            'req_ling' => (bool) $this->req_ling,
            'observ' => $this->observ,
            'observ_tags' => $this->observ_tags ?? [],
            'year' => $this->year,
            'distances' => $this->distances,
        ];
    }
}
