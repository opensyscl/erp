import { usePage } from '@inertiajs/react';

// @ts-ignore
declare var route: any;

/**
 * Custom hook for generating tenant-aware routes.
 * Automatically handles both domain-based and path-based tenant routing.
 */
export function useTenantRoute() {
    const { tenant } = usePage().props as any;

    const tRoute = (name: string, params: any = {}, absolute: boolean = false): string => {
        const currentHost = window.location.host;
        const currentPath = window.location.pathname;

        try {
            // 1. If tenant is available from props
            if (tenant && tenant.slug) {
                const isDomainRoute = tenant.domain && currentHost.includes(tenant.domain);

                if (isDomainRoute) {
                    // Domain-based route: Build path manually
                    // Use path-based route to get the structure, then strip /app/{tenant}
                    const fullUrl = route(`tenant.${name}`, { ...params, tenant: tenant.slug }, true);
                    try {
                        const url = new URL(fullUrl);
                        // Path: /app/slug/inventory -> /inventory
                        const pathWithoutTenant = url.pathname.replace(`/app/${tenant.slug}`, '');
                        return pathWithoutTenant || '/';
                    } catch {
                        return '/';
                    }
                } else {
                    // Path-based route: e.g., /app/kisto/inventory
                    return route(`tenant.${name}`, { ...params, tenant: tenant.slug }, absolute);
                }
            }

            // 2. Fallback: Try to extract tenant slug from URL path
            const pathParts = currentPath.split('/');
            if (pathParts[1] === 'app' && pathParts[2]) {
                const slugFromUrl = pathParts[2];
                return route(`tenant.${name}`, { ...params, tenant: slugFromUrl }, absolute);
            }

            console.error('useTenantRoute: Could not determine tenant context.');
            return '/';
        } catch (error) {
            console.error('useTenantRoute error:', error);
            return '/';
        }
    };

    return tRoute;
}
