<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrganizationInvitationRequest;
use App\Http\Resources\Api\V1\OrganizationInvitationResource;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class OrganizationInvitationController extends Controller
{
    public function index(
        Request $request,
        Organization $organization,
    ): JsonResponse {
        abort_unless(
            $request->user()
                ->organizations()
                ->whereKey($organization->getKey())
                ->wherePivotIn('role', [
                    Organization::ROLE_OWNER,
                    Organization::ROLE_ADMIN,
                ])
                ->exists(),
            403,
        );

        $invitations = $organization->invitations()
            ->with(['organization', 'inviter'])
            ->latest()
            ->get();

        return ApiResponse::success(
            'Organization invitations retrieved successfully.',
            [
                'invitations' => OrganizationInvitationResource::collection($invitations),
            ],
        );
    }

    public function store(
        StoreOrganizationInvitationRequest $request,
        Organization $organization,
    ): JsonResponse {
        $email = mb_strtolower(trim($request->validated('email')));
        $role = $request->validated('role', Organization::ROLE_MEMBER);

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existingUser && $organization->users()->whereKey($existingUser->getKey())->exists()) {
            return ApiResponse::error(
                'This user is already a member of the organization.',
                [],
                422,
            );
        }

        $pendingInvitationExists = $organization->invitations()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();

        if ($pendingInvitationExists) {
            return ApiResponse::error(
                'A pending invitation already exists for this email.',
                [],
                422,
            );
        }

        $token = Str::random(64);

        $invitation = DB::transaction(function () use (
            $organization,
            $request,
            $email,
            $role,
            $token,
        ): OrganizationInvitation {
            return $organization->invitations()->create([
                'invited_by' => $request->user()->getKey(),
                'email' => $email,
                'token_hash' => hash('sha256', $token),
                'role' => $role,
                'expires_at' => now()->addDays(7),
            ]);
        });

        $invitation->load(['organization', 'inviter']);

        if (! $this->deliverInvitation($invitation, $token)) {
            return ApiResponse::error(
                'The invitation was saved, but the email could not be sent. Retry using the resend endpoint.',
                ['invitation_id' => $invitation->public_id],
                503,
            );
        }

        return ApiResponse::success(
            'Organization invitation created successfully.',
            [
                'invitation' => new OrganizationInvitationResource($invitation),
            ],
            201,
        );
    }

    public function resend(
        Request $request,
        Organization $organization,
        OrganizationInvitation $invitation,
    ): JsonResponse {
        $this->authorizeInvitationManagement($request, $organization);

        abort_unless(
            $invitation->organization_id === $organization->getKey(),
            404,
        );

        if (
            $invitation->accepted_at !== null
            || $invitation->revoked_at !== null
        ) {
            return ApiResponse::error(
                'Only unaccepted, non-revoked invitations can be resent.',
                [],
                422,
            );
        }

        $token = Str::random(64);

        $invitation->update([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->load(['organization', 'inviter']);

        if (! $this->deliverInvitation($invitation, $token)) {
            return ApiResponse::error(
                'The invitation was updated, but the email could not be sent. Retry the resend request.',
                ['invitation_id' => $invitation->public_id],
                503,
            );
        }

        return ApiResponse::success(
            'Organization invitation resent successfully.',
            ['invitation' => new OrganizationInvitationResource($invitation)],
        );
    }

    public function destroy(
        Request $request,
        Organization $organization,
        OrganizationInvitation $invitation,
    ): JsonResponse {
        abort_unless(
            $request->user()
                ->organizations()
                ->whereKey($organization->getKey())
                ->wherePivotIn('role', [
                    Organization::ROLE_OWNER,
                    Organization::ROLE_ADMIN,
                ])
                ->exists(),
            403,
        );

        abort_unless(
            $invitation->organization_id === $organization->getKey(),
            404,
        );

        if (
            $invitation->accepted_at !== null
            || $invitation->revoked_at !== null
            || $invitation->expires_at->isPast()
        ) {
            return ApiResponse::error(
                'Only pending invitations can be revoked.',
                [],
                422,
            );
        }

        $invitation->update(['revoked_at' => now()]);

        return ApiResponse::success('Organization invitation revoked successfully.');
    }

    private function authorizeInvitationManagement(
        Request $request,
        Organization $organization,
    ): void {
        abort_unless(
            $request->user()
                ->organizations()
                ->whereKey($organization->getKey())
                ->wherePivotIn('role', [
                    Organization::ROLE_OWNER,
                    Organization::ROLE_ADMIN,
                ])
                ->exists(),
            403,
        );
    }

    private function deliverInvitation(
        OrganizationInvitation $invitation,
        string $token,
    ): bool {
        try {
            Notification::route('mail', $invitation->email)->notify(
                new OrganizationInvitationNotification($invitation, $token),
            );

            return true;
        } catch (Throwable $exception) {
            Log::error('Organization invitation email delivery failed.', [
                'invitation_id' => $invitation->public_id,
                'organization_id' => $invitation->organization->public_id,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }
}
