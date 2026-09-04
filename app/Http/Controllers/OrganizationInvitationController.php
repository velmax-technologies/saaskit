<?php

namespace App\Http\Controllers;

use App\Actions\Tenancy\CreateOrganizationInvitation;
use App\Http\Requests\Api\Organization\StoreOrganizationInvitationRequest;
use App\Http\Resources\Api\Organization\OrganizationInvitationResource;
use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class OrganizationInvitationController extends Controller
{
    public function store(
        StoreOrganizationInvitationRequest $request,
        Organization $organization,
        CreateOrganizationInvitation $createInvitation,
    ): JsonResponse {
        $this->authorize('inviteMembers', $organization);

        $result = $createInvitation->execute(
            invitedBy: $request->user(),
            organization: $organization,
            email: $request->string('email')->toString(),
            role: OrganizationMemberRole::from(
                $request->string('role')->toString(),
            ),
        );

        return ApiResponse::created(
            data: [
                'invitation' => new OrganizationInvitationResource(
                    $result->invitation,
                ),
            ],
            message: 'Invitation created successfully.',
        );
    }
}
