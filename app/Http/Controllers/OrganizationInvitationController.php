<?php

namespace App\Http\Controllers;

use App\Actions\Tenancy\AcceptOrganizationInvitation;
use App\Actions\Tenancy\CreateOrganizationInvitation;
use App\Enums\OrganizationMemberRole;
use App\Http\Requests\Api\Organization\AcceptOrganizationInvitationRequest;
use App\Http\Requests\Api\Organization\ListOrganizationInvitationRequest;
use App\Http\Requests\Api\Organization\StoreOrganizationInvitationRequest;
use App\Http\Resources\Api\Organization\OrganizationInvitationResource;
use App\Http\Resources\Api\Organization\OrganizationMemberResource;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use App\Actions\Tenancy\CancelOrganizationInvitation;
use App\Actions\Tenancy\ResendOrganizationInvitation;
use Illuminate\Http\Response;

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

    public function accept(
        AcceptOrganizationInvitationRequest $request,
        OrganizationInvitation $invitation,
        AcceptOrganizationInvitation $acceptInvitation,
    ): JsonResponse {
        $membership = $acceptInvitation->execute(
            user: $request->user(),
            invitation: $invitation,
            token: $request->string('token')->toString(),
        );

        $membership->load([
            'user',
            'organization',
        ]);

        return ApiResponse::success(
            data: [
                'membership' => new OrganizationMemberResource($membership),
            ],
            message: 'Invitation accepted successfully.',
        );
    }

    public function resend(
        Organization $organization,
        OrganizationInvitation $invitation,
        ResendOrganizationInvitation $resendInvitation,
    ): JsonResponse {
        $this->authorize('inviteMembers', $organization);

        $result = $resendInvitation->execute(
            organization: $organization,
            invitation: $invitation,
        );

        return ApiResponse::success(
            data: [
                'invitation' => new OrganizationInvitationResource(
                    $result->invitation,
                ),
            ],
            message: 'Invitation resent successfully.',
        );
    }

    public function cancel(
        Organization $organization,
        OrganizationInvitation $invitation,
        CancelOrganizationInvitation $cancelInvitation,
    ): Response {
        $this->authorize('inviteMembers', $organization);

        $cancelInvitation->execute(
            organization: $organization,
            invitation: $invitation,
        );

        return ApiResponse::noContent();
    }
}
