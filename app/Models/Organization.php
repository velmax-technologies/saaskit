<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'description',
])]
class Organization extends Model
{
    use HasPublicId;

    protected string $publicIdPrefix = 'org';

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }
}
