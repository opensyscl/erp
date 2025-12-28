<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // Use a closure so it's evaluated AFTER route middleware (IdentifyTenant) runs
            'tenant' => fn () => $this->getTenantData(),
        ];
    }

    /**
     * Get tenant data for sharing with Inertia.
     */
    private function getTenantData(): ?array
    {
        $tenant = app(\App\Services\CurrentTenant::class)->get();

        if (!$tenant) {
            return null;
        }

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'domain' => $tenant->domain,
            'brand_color' => $tenant->getSetting('brand_color', '#3b82f6'),
            'logo' => $tenant->getSetting('logo'),
        ];
    }
}
