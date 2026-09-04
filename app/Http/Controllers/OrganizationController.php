<?php

namespace App\Http\Controllers;

use App\Actions\Tenancy\CreateOrganization;
use App\Actions\Tenancy\LeaveOrganization;
use App\Actions\Tenancy\RejoinOrganization;
use App\Actions\Tenancy\TransferOrganizationOwnership;
use App\Http\Requests\Api\Organization\StoreOrganizationRequest;
use App\Http\Requests\Api\Organization\TransferOrganizationOwnershipRequest;
use App\Http\Resources\Api\Organization\OrganizationMemberResource;
use App\Http\Resources\Api\Organization\OrganizationResource;
use App\Models\Organization;
use App\Support\Api\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizations = Organization::query()
            ->whereHas('members', function (Builder $query) use ($request): void {
                $query
                    ->where('user_id', $request->user()->id)
                    ->where('status', 'active');
            })
            ->latest()
            ->paginate();

        return ApiResponse::success(
            data: [
                'organizations' => OrganizationResource::collection(
                    $organizations->items(),
                )->resolve(),
                'pagination' => [
                    'current_page' => $organizations->currentPage(),
                    'last_page' => $organizations->lastPage(),
                    'per_page' => $organizations->perPage(),
                    'total' => $organizations->total(),
                    'links' => [
                        'first' => $organizations->url(1),
                        'last' => $organizations->url($organizations->lastPage()),
                        'previous' => $organizations->previousPageUrl(),
                        'next' => $organizations->nextPageUrl(),
                    ],
                ],
            ],
            message: 'Organizations retrieved successfully.',
        );
    }

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

    public function show(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return ApiResponse::success(
            data: [
                'organization' => new OrganizationResource($organization),
            ],
            message: 'Organization retrieved successfully.',
        );
    }

    public function transferOwnership(
        TransferOrganizationOwnershipRequest $request,
        Organization $organization,
        TransferOrganizationOwnership $action,
    ): JsonResponse {
        $target = $organization->members()
            ->where(
                'public_id',
                $request->string('member')->toString(),
            )
            ->firstOrFail();

        $member = $action->execute(
            organization: $organization,
            target: $target,
        );

        return ApiResponse::success(
            data: [
                'member' => new OrganizationMemberResource($member),
            ],
            message: 'Organization ownership transferred successfully.',
        );
    }

    public function leave(
        Request $request,
        Organization $organization,
        LeaveOrganization $action,
    ): Response {
        $member = $organization->members()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $this->authorize('leave', $organization);

        $action->execute($member);

        return ApiResponse::noContent();
    }

    public function rejoin(
        Request $request,
        Organization $organization,
        RejoinOrganization $action,
    ): JsonResponse {
        $member = $organization->members()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $member = $action->execute($member);

        return ApiResponse::success(
            data: [
                'member' => new OrganizationMemberResource($member),
            ],
            message: 'You have rejoined the organization successfully.',
        );
    }
}
