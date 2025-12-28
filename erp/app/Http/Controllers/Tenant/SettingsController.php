<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\CurrentTenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display the settings page.
     */
    public function index(): Response
    {
        $tenant = $this->currentTenant->get();

        return Inertia::render('Tenant/Settings/Index', [
            'settings' => [
                'brand_color' => $tenant->getSetting('brand_color', '#3b82f6'),
                'logo' => $tenant->getSetting('logo'),
            ],
        ]);
    }

    /**
     * Update the settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'brand_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'logo' => ['nullable', 'image', 'max:2048'], // 2MB Max
        ]);

        $tenant = $this->currentTenant->get();
        $tenant->setSetting('brand_color', $validated['brand_color']);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('tenants/' . $tenant->id . '/logo', 'public');
            $tenant->setSetting('logo', '/storage/' . $path);
        }

        $tenant->save();

        return back()->with('success', 'Configuración actualizada correctamente.');
    }
}
