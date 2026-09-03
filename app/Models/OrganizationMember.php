<?php

namespace App\Models;

use App\Enums\OrganizationMemberRole;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'organization_id',
    'role',
    'status',
])]
class OrganizationMember extends Model
{
    use HasPublicId;

    protected string $publicIdPrefix = 'mem';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    protected function casts(): array
    {
        return [
            'role' => OrganizationMemberRole::class,
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === OrganizationMemberRole::OWNER;
    }

    public function isAdmin(): bool
    {
        return $this->role === OrganizationMemberRole::ADMIN;
    }

    public function isMember(): bool
    {
        return $this->role === OrganizationMemberRole::MEMBER;
    }

    public function hasRole(OrganizationMemberRole $role): bool
    {
        return $this->role === $role;
    }
}
