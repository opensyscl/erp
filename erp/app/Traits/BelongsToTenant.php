<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use App\Services\CurrentTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait.
     */
    protected static function bootBelongsToTenant(): void
    {
        // Add global scope
        static::addGlobalScope(new TenantScope());

        // Auto-assign tenant_id on creating
        static::creating(function ($model) {
            $currentTenant = app(CurrentTenant::class);

            if ($currentTenant->check() && empty($model->tenant_id)) {
                $model->tenant_id = $currentTenant->id();
            }
        });
    }

    /**
     * Get the tenant that owns this model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope a query to a specific tenant.
     */
    public function scopeForTenant($query, Tenant|int $tenant)
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $query->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to include all tenants (bypass scope).
     */
    public function scopeWithoutTenantScope($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
