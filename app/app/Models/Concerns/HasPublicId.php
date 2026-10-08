<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasPublicId
{
    protected static function bootHasPublicId(): void
    {
        static::creating(function ($model): void {
            if (blank($model->public_id)) {
                $model->public_id = static::publicIdPrefix().Str::ulid();
            }
        });
    }

    public static function publicIdPrefix(): string
    {
        return 'res_';
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
