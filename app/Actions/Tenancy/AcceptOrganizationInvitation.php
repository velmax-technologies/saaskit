<?php

namespace App\Actions\Tenancy;

use App\Enums\OrganizationInvitationStatus;
use App\Enums\OrganizationMemberStatus;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcceptOrganizationInvitation
{
    public function execute(
        User $user,
        OrganizationInvitation $invitation,
        string $token,
    ): OrganizationMember {
        /*
         * Expire the invitation atomically before starting the
         * acceptance transaction.
         *
         * This ensures an expired invitation is persisted as
         * "expired" instead of being rolled back by the transaction.
         */
        $expired = OrganizationInvitation::query()
            ->whereKey($invitation->id)
            ->where(
                'status',
                OrganizationInvitationStatus::PENDING->value,
            )
            ->where('expires_at', '<=', now())
            ->update([
                'status' => OrganizationInvitationStatus::EXPIRED->value,
            ]);

        if ($expired > 0) {
            throw ValidationException::withMessages([
                'invitation' => [
                    'This invitation has expired.',
                ],
            ]);
        }

        return DB::transaction(function () use (
            $user,
            $invitation,
            $token,
        ): OrganizationMember {
            /*
             * Lock the invitation row.
             *
             * This prevents concurrent requests from accepting the
             * same invitation simultaneously.
             */
            $invitation = OrganizationInvitation::query()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Re-check the invitation status after acquiring
             * the database lock.
             */
            if ($invitation->status !== OrganizationInvitationStatus::PENDING) {
                throw ValidationException::withMessages([
                    'invitation' => [
                        'This invitation is no longer pending.',
                    ],
                ]);
            }

            /*
             * Re-check expiration against the locked/current row.
             *
             * We cannot persist the expired status here and then throw,
             * because the transaction would roll the update back.
             *
             * The atomic expiration check before the transaction handles
             * the normal expiration path.
             */
            if ($invitation->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'invitation' => [
                        'This invitation has expired.',
                    ],
                ]);
            }

            /*
             * Verify the supplied token against the current invitation.
             */
            if (! hash_equals(
                $invitation->token_hash,
                hash('sha256', $token),
            )) {
                throw ValidationException::withMessages([
                    'token' => [
                        'The invitation token is invalid.',
                    ],
                ]);
            }

            /*
             * Verify that the authenticated user owns the email address
             * to which the invitation was issued.
             */
            if (
                strtolower($user->email)
                !== strtolower($invitation->email)
            ) {
                throw ValidationException::withMessages([
                    'invitation' => [
                        'This invitation was sent to a different email address.',
                    ],
                ]);
            }

            /*
             * Lock the existing membership, if one exists.
             *
             * The unique constraint on:
             *
             *   user_id + organization_id
             *
             * guarantees that only one membership row can exist for
             * this user and organization.
             */
            $existingMembership = OrganizationMember::query()
                ->where(
                    'organization_id',
                    $invitation->organization_id,
                )
                ->where(
                    'user_id',
                    $user->id,
                )
                ->lockForUpdate()
                ->first();

            /*
             * An active member cannot accept the invitation again.
             */
            if (
                $existingMembership
                && $existingMembership->status
                    === OrganizationMemberStatus::ACTIVE
            ) {
                throw ValidationException::withMessages([
                    'invitation' => [
                        'You are already a member of this organization.',
                    ],
                ]);
            }

            /*
             * Reactivate an existing LEFT or REMOVED membership.
             *
             * The existing membership row is reused, preserving its
             * public ID and preventing duplicate memberships.
             */
            if ($existingMembership) {
                $existingMembership->update([
                    'role' => $invitation->role->value,
                    'status' => OrganizationMemberStatus::ACTIVE->value,
                ]);

                $membership = $existingMembership->fresh([
                    'user',
                    'organization',
                ]);
            } else {
                /*
                 * Create a membership for a user who has never belonged
                 * to this organization.
                 */
                $membership = OrganizationMember::create([
                    'user_id' => $user->id,
                    'organization_id' => $invitation->organization_id,
                    'role' => $invitation->role->value,
                    'status' => OrganizationMemberStatus::ACTIVE->value,
                ]);

                $membership->load([
                    'user',
                    'organization',
                ]);
            }

            /*
             * Consume the invitation.
             *
             * Because the invitation row is locked, another concurrent
             * request will see ACCEPTED after this transaction commits.
             */
            $invitation->update([
                'status' => OrganizationInvitationStatus::ACCEPTED->value,
                'accepted_at' => now(),
            ]);

            return $membership;
        });
    }
}