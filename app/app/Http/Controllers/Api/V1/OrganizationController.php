<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrganizationRequest;
use App\Http\Resources\Api\V1\MembershipResource;
use App\Http\Resources\Api\V1\OrganizationResource;
use App\Models\Membership;
use App\Models\Organization;
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
