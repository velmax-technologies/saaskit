<?php

namespace App\Models;

use App\Enums\OrganizationInvitationStatus;
use App\Enums\OrganizationMemberRole;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'invited_by',
    'email',
    'role',
    'token_hash',
    'status',
    'expires_at',
    'accepted_at',
    'cancelled_at',
])]
class OrganizationInvitation extends Model
{
    use HasPublicId;

    protected string $publicIdPrefix = 'inv';

    protected $hidden = [
        'id',
        'token_hash',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    protected function casts(): array
    {
        return [
            'status' => OrganizationInvitationStatus::class,
            'role' => OrganizationMemberRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === OrganizationInvitationStatus::PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === OrganizationInvitationStatus::ACCEPTED;
    }

    public function isExpired(): bool
    {
        return $this->status === OrganizationInvitationStatus::EXPIRED
            || (
                $this->status === OrganizationInvitationStatus::PENDING
                && $this->expires_at->isPast()
            );
    }

    public function isCancelled(): bool
    {
        return $this->status === OrganizationInvitationStatus::CANCELLED;
    }
}
