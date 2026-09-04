<?php

namespace App\Http\Controllers;

use App\Actions\Tenancy\CreateOrganizationInvitation;
use App\Http\Requests\Api\Organization\ListOrganizationInvitationRequest;
use App\Http\Requests\Api\Organization\StoreOrganizationInvitationRequest;
use App\Http\Resources\Api\Organization\OrganizationInvitationResource;
use App\Enums\OrganizationMemberRole;
use App\Models\Organization;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class OrganizationInvitationController extends Controller
{
    public function index(
        ListOrganizationInvitationRequest $request,
        Organization $organization,
    ): JsonResponse {
        $this->authorize('viewMembers', $organization);

        $query = $organization->invitations()
            ->latest('id');

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString(),
            );
        }

        if ($request->filled('email')) {
            $query->where(
                'email',
                'like',
                '%'.$request->string('email')->toString().'%',
            );
        }

        $invitations = $query->paginate(
            perPage: min(
                max((int) $request->integer('per_page', 15), 1),
                100,
            ),
        );

        return ApiResponse::paginated(
            resource: OrganizationInvitationResource::collection(
                $invitations,
            ),
            resourceKey: 'invitations',
            message: 'Organization invitations retrieved successfully.',
        );
    }

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
