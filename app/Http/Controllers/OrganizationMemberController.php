<?php

namespace App\Http\Controllers;

use App\Actions\Tenancy\RemoveOrganizationMember;
use App\Actions\Tenancy\UpdateOrganizationMemberRole;
use App\Enums\OrganizationMemberRole;
use App\Http\Requests\Api\Organization\UpdateOrganizationMemberRequest;
use App\Http\Resources\Api\Organization\OrganizationMemberResource;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Enums\OrganizationMemberStatus;

class OrganizationMemberController extends Controller
{
    public function index(
        Request $request,
        Organization $organization,
    ): JsonResponse {
        $this->authorize('viewMembers', $organization);

        $members = $organization->members()
            ->with('user')
            ->where('status', OrganizationMemberStatus::ACTIVE->value)
            ->orderBy('id')
            ->paginate(
                perPage: min(
                    max((int) $request->integer('per_page', 15), 1),
                    100,
                ),
            );

        return ApiResponse::paginated(
            resource: OrganizationMemberResource::collection($members),
            resourceKey: 'members',
            message: 'Organization members retrieved successfully.',
        );
    }

    public function update(
        UpdateOrganizationMemberRequest $request,
        Organization $organization,
        OrganizationMember $member,
        UpdateOrganizationMemberRole $action,
    ): JsonResponse {
        abort_unless(
            $member->organization_id === $organization->id,
            404,
        );

        abort_unless(
            $member->status === OrganizationMemberStatus::ACTIVE,
            404,
        );

        $updatedMember = $action->execute(
            member: $member,
            role: OrganizationMemberRole::from(
                $request->validated('role'),
            ),
        );

        return ApiResponse::success(
            data: [
                'member' => new OrganizationMemberResource(
                    $updatedMember,
                ),
            ],
            message: 'Organization member role updated successfully.',
        );
    }

    public function destroy(
        Organization $organization,
        OrganizationMember $member,
        RemoveOrganizationMember $action,
    ): Response {
        abort_unless(
            $member->organization_id === $organization->id,
            404,
        );

        $this->authorize(
            'removeMembers',
            [$organization, $member],
        );

        $action->execute($member);

        return ApiResponse::noContent();
    }
}
