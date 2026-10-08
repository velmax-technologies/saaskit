<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrganizationRequest;
use App\Http\Resources\Api\V1\OrganizationResource;
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
