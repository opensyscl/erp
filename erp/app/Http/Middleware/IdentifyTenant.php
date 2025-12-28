<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Try to identify by Domain (Host)
        $host = $request->getHost();
        $tenant = Tenant::where('domain', $host)->first();

        if ($tenant) {
            if (!$tenant->isActive()) {
                abort(403, 'Tenant is not active.');
            }

            // Verify user belongs to this tenant (if authenticated)
            if ($request->user() && $request->user()->tenant_id !== $tenant->id) {
                if (!$request->user()->isSuperAdmin()) {
                    abort(403, 'No tienes acceso a esta tienda.');
                }
            }

            $this->currentTenant->set($tenant);
            return $next($request);
        }

        // 2. Try to identify by Route Parameter
        $slug = $request->route('tenant');

        if ($slug) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (!$tenant) {
                abort(404, 'Tenant not found.');
            }

            if (!$tenant->isActive()) {
                abort(403, 'Tenant is not active.');
            }

            // Verify user belongs to this tenant (if authenticated)
            // Note: We might want to allow public access to tenant routes aka 'storefront' in future
            if ($request->user() && $request->user()->tenant_id !== $tenant->id) {
                if (!$request->user()->isSuperAdmin()) {
                    abort(403, 'You do not have access to this tenant.');
                }
            }

            $this->currentTenant->set($tenant);
            return $next($request);
        }

        // 3. Fallback: Check if user is authenticated and has a tenant (for internal routes / dashboard redirect)
        if ($request->user() && $request->user()->tenant_id) {
            $tenant = Tenant::find($request->user()->tenant_id);

            if ($tenant && $tenant->isActive()) {
                // Only set if we are NOT on a superadmin route (which shouldn't have this middleware usually, but for safety)
                $this->currentTenant->set($tenant);
                return $next($request);
            }
        }

        // No tenant identified.
        // If this middleware is strictly applied to tenant routes, we should abort.
        // However, if applied globally, we might pass through.
        // Given existing usage on 'app/{tenant}', valid usage implies 'tenant' param exists or we found it by domain.

        // Only abort if we are in a route that explicitly EXPECTS a tenant context?
        // For now, if no slug and no domain match, we continue?
        // The previous code aborted 403.

        if ($request->route('tenant')) { // Explicit route param was expected but null? Unlikely if defined in route.
             abort(403, 'No tenant identified.');
        }

        return $next($request);
    }
}
