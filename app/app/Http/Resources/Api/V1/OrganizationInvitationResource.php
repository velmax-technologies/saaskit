<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'email' => $this->email,
            'role' => $this->role,
            'organization' => [
                'id' => $this->organization->public_id,
                'name' => $this->organization->name,
            ],
            'invited_by' => $this->inviter?->public_id,
            'expires_at' => $this->expires_at,
            'accepted_at' => $this->accepted_at,
            'revoked_at' => $this->revoked_at,
            'created_at' => $this->created_at,
        ];
    }
}
