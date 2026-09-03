<?php

namespace App\Http\Controllers;

use App\Actions\Tenancy\CreateOrganization;
use App\Http\Requests\Api\Organization\StoreOrganizationRequest;
use App\Http\Resources\Api\Organization\OrganizationResource;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class OrganizationController extends Controller
{
    public function store(
        StoreOrganizationRequest $request,
        CreateOrganization $createOrganization,
    ): JsonResponse {
        $organization = $createOrganization->execute(
            user: $request->user(),
            name: $request->string('name')->toString(),
            slug: $request->string('slug')->toString(),
            description: $request->input('description'),
        );

        return ApiResponse::created(
            data: [
                'organization' => new OrganizationResource($organization),
            ],
            message: 'Organization created successfully.',
        );
    }
}
