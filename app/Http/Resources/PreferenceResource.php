<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreferenceResource extends JsonResource
{
    /**
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
            'user_list_id' => $this->user_list_id,
            'vacancy_id' => $this->vacancy_id,
            'position' => $this->position,
            'status' => $this->status,
            'notes' => $this->notes,
            'vacancy' => $this->whenLoaded('vacancy', function () {
                return (new VacancyResource($this->vacancy))->withDistances($this->distances);
            }),
            'distances' => $this->distances,
        ];
    }
}
