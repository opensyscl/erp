<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Services\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $currentTenant = app(CurrentTenant::class);

        if ($currentTenant->check()) {
            $builder->where($model->getTable() . '.tenant_id', $currentTenant->id());
        }
    }
}
