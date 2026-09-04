<?php

namespace App\Http\Resources\Api\Organization;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,

            'user' => [
                'id' => $this->user->public_id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],

            'organization' => [
                'id' => $this->organization->public_id,
                'name' => $this->organization->name,
                'slug' => $this->organization->slug,
            ],

            'role' => $this->role->value,
            'status' => $this->status,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
