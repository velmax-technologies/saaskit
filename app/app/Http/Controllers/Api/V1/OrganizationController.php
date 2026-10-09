<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteOrganizationRequest;
use App\Http\Requests\Api\V1\RemoveOrganizationMemberRequest;
use App\Http\Requests\Api\V1\StoreOrganizationMemberRequest;
use App\Http\Requests\Api\V1\StoreOrganizationRequest;
use App\Http\Requests\Api\V1\UpdateOrganizationMemberRequest;
use App\Http\Requests\Api\V1\UpdateOrganizationRequest;
use App\Http\Resources\Api\V1\MembershipResource;
use App\Http\Resources\Api\V1\OrganizationResource;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizations = $request->user()
            ->organizations()
            ->latest('organizations.created_at')
            ->get();

        return ApiResponse::success(
            'Organizations retrieved successfully.',
            [
                'organizations' => OrganizationResource::collection($organizations),
            ],
        );
    }

    public function storeMember(
        StoreOrganizationMemberRequest $request,
        Organization $organization,
    ): JsonResponse {
        $user = User::query()
            ->where('public_id', $request->validated('user_id'))
            ->firstOrFail();

        if ($organization->users()->whereKey($user->getKey())->exists()) {
            return ApiResponse::error(
                'User is already a member of this organization.',
                [],
                422,
            );
        }

        $organization->users()->attach($user, [
            'role' => $request->validated('role', Organization::ROLE_MEMBER),
        ]);

        $membership = Membership::query()
            ->with(['organization', 'user'])
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        return ApiResponse::success(
            'Organization member added successfully.',
            [
                'member' => new MembershipResource($membership),
            ],
            201,
        );
    }

    public function updateMember(
        UpdateOrganizationMemberRequest $request,
        Organization $organization,
        Membership $membership,
    ): JsonResponse {
        $membership->update([
            'role' => $request->validated('role'),
        ]);

        $membership->load(['organization', 'user']);

        return ApiResponse::success(
            'Organization member role updated successfully.',
            [
                'member' => new MembershipResource($membership),
            ],
        );
    }

    public function destroyMember(
        RemoveOrganizationMemberRequest $request,
        Organization $organization,
        Membership $membership,
    ): JsonResponse {
        $membership->delete();

        return ApiResponse::success(
            'Organization member removed successfully.',
        );
    }

    public function members(
        Request $request,
        Organization $organization,
    ): JsonResponse {
        abort_unless(
            $request->user()
                ->organizations()
                ->whereKey($organization->getKey())
                ->exists(),
            404,
        );

        $memberships = Membership::query()
            ->with(['organization', 'user'])
            ->where('organization_id', $organization->getKey())
            ->latest('created_at')
            ->get();

        return ApiResponse::success(
            'Organization members retrieved successfully.',
            [
                'members' => MembershipResource::collection($memberships),
            ],
        );
    }

    public function destroy(
        DeleteOrganizationRequest $request,
        Organization $organization,
    ): JsonResponse {
        $organization->delete();

        return ApiResponse::success(
            'Organization deleted successfully.',
        );
    }

    public function update(
        UpdateOrganizationRequest $request,
        Organization $organization,
    ): JsonResponse {
        $organization->update($request->validated());

        return ApiResponse::success(
            'Organization updated successfully.',
            [
                'organization' => new OrganizationResource($organization->refresh()),
            ],
        );
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = Organization::create($request->validated());

        $request->user()->organizations()->attach($organization, [
            'role' => Organization::ROLE_OWNER,
        ]);

        return ApiResponse::success(
            'Organization created successfully.',
            [
                'organization' => new OrganizationResource($organization),
                'role' => Organization::ROLE_OWNER,
            ],
            201,
        );
    }
}
