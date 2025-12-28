<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;

class CurrentTenant
{
    /**
     * The current tenant instance.
     */
    protected ?Tenant $tenant = null;

    /**
     * Set the current tenant.
     */
    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Get the current tenant.
     */
    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Get the current tenant ID.
     */
    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    /**
     * Check if a tenant is currently set.
     */
    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Clear the current tenant.
     */
    public function forget(): void
    {
        $this->tenant = null;
    }

    /**
     * Get the current tenant or throw an exception.
     */
    public function getOrFail(): Tenant
    {
        if (!$this->tenant) {
            throw new \RuntimeException('No tenant is currently set.');
        }

        return $this->tenant;
    }
}
