<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'role' => $this->role,
            'organization' => $this->whenLoaded('organization', fn () => [
                'id' => $this->organization->public_id,
                'name' => $this->organization->name,
                'slug' => $this->organization->slug,
            ]),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->public_id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
