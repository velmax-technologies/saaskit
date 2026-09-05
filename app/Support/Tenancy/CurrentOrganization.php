<?php

namespace App\Support\Tenancy;

use App\Models\Organization;
use LogicException;

final class CurrentOrganization
{
    private ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function get(): Organization
    {
        if ($this->organization === null) {
            throw new LogicException(
                'No current organization has been resolved.',
            );
        }

        return $this->organization;
    }

    public function has(): bool
    {
        return $this->organization !== null;
    }

    public function clear(): void
    {
        $this->organization = null;
    }

    public function id(): int
    {
        return $this->get()->id;
    }

    public function publicId(): string
    {
        return $this->get()->public_id;
    }

    public function model(): Organization
    {
        return $this->get();
    }
}