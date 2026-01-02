<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Config;

class SetTenantTimezone
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Try to get the current tenant from the request if it was resolved by IdentifyCompany
        // Or resolve it from the container manually if needed.
        // Assuming CurrentTenant service is bound.

        try {
            $currentTenant = app(\App\Services\CurrentTenant::class);

            if ($currentTenant->check()) {
                $tenant = $currentTenant->get();
                $timezone = $tenant->getSetting('timezone');

                if ($timezone) {
                    Config::set('app.timezone', $timezone);
                    date_default_timezone_set($timezone);
                }
            }
        } catch (\Exception $e) {
            // Fails silently if tenant not identified or service not ready
        }

        return $next($request);
    }
}
