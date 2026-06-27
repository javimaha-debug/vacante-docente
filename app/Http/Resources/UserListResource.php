<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_token' => $this->session_token,
            'specialty_id' => $this->specialty_id,
            'home_address' => $this->home_address,
            'home_lat' => $this->home_lat !== null ? (float) $this->home_lat : null,
            'home_lng' => $this->home_lng !== null ? (float) $this->home_lng : null,
            'has_home' => $this->hasHome(),
            'specialty' => new SpecialtyResource($this->whenLoaded('specialty')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
