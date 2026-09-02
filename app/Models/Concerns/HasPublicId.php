<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasPublicId
{
    /**
     * Boot the HasPublicId trait.
     */
    protected static function bootHasPublicId(): void
    {
        static::creating(function ($model): void {
            if (empty($model->public_id)) {
                $model->public_id = $model->generatePublicId();
            }
        });
    }

    /**
     * Get the public identifier prefix.
     */
    public function getPublicIdPrefix(): string
    {
        return property_exists($this, 'publicIdPrefix')
            ? $this->publicIdPrefix
            : 'id';
    }

    /**
     * Generate a new public identifier.
     */
    public function generatePublicId(): string
    {
        return $this->getPublicIdPrefix().'_'.Str::ulid();
    }

    /**
     * Use the public identifier for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
