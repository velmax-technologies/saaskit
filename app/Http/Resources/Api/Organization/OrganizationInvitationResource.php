<?php

namespace App\Http\Resources\Api\Organization;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,

            'email' => $this->email,

            'role' => $this->role->value,

            'status' => $this->status->value,

            'expires_at' => $this->expires_at?->toISOString(),

            'accepted_at' => $this->accepted_at?->toISOString(),

            'cancelled_at' => $this->cancelled_at?->toISOString(),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
