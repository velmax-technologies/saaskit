<?php

namespace App\Http\Controllers;

use App\Http\Resources\Api\Organization\OrganizationMemberResource;
use App\Models\Organization;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationMemberController extends Controller
{
    public function index(
        Request $request,
        Organization $organization,
    ): JsonResponse {
        $this->authorize('viewMembers', $organization);

        $members = $organization->members()
            ->with('user')
            ->where('status', 'active')
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
}
