<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AcceptOrganizationInvitationRequest;
use App\Models\OrganizationInvitation;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AcceptOrganizationInvitationController extends Controller
{
    public function __invoke(
        AcceptOrganizationInvitationRequest $request,
        OrganizationInvitation $invitation,
    ): JsonResponse {
        $user = $request->user();
        $token = $request->validated('token');

        $result = DB::transaction(function () use ($invitation, $user, $token): array {
            $lockedInvitation = OrganizationInvitation::query()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedInvitation->accepted_at !== null
                || $lockedInvitation->revoked_at !== null
                || $lockedInvitation->expires_at->isPast()
            ) {
                return [
                    'error' => 'This invitation is no longer valid.',
                    'status' => 422,
                ];
            }

            if (! hash_equals($lockedInvitation->token_hash, hash('sha256', $token))) {
                return [
                    'error' => 'The invitation token is invalid.',
                    'status' => 422,
                ];
            }

            if (mb_strtolower(trim($user->email)) !== mb_strtolower(trim($lockedInvitation->email))) {
                return [
                    'error' => 'This invitation was issued to a different email address.',
                    'status' => 403,
                ];
            }

            $organization = $lockedInvitation->organization;

            if ($organization->users()->whereKey($user->getKey())->exists()) {
                return [
                    'error' => 'You are already a member of this organization.',
                    'status' => 422,
                ];
            }

            $organization->users()->attach($user->getKey(), [
                'role' => $lockedInvitation->role,
            ]);

            $lockedInvitation->update(['accepted_at' => now()]);

            return [
                'organization' => [
                    'id' => $organization->public_id,
                    'name' => $organization->name,
                ],
                'role' => $lockedInvitation->role,
            ];
        });

        if (isset($result['error'])) {
            return ApiResponse::error($result['error'], [], $result['status']);
        }

        return ApiResponse::success(
            'Organization invitation accepted successfully.',
            $result,
        );
    }
}
