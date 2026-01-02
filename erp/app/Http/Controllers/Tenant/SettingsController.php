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
                'company_name' => $tenant->getSetting('company_name'),
                'company_rut' => $tenant->getSetting('company_rut'),
                'company_address' => $tenant->getSetting('company_address'),
                'company_phone' => $tenant->getSetting('company_phone'),
                'company_email' => $tenant->getSetting('company_email'),
                'layout_template' => $tenant->layout_template ?? 'modern',
                'dashboard_shell' => $tenant->getSetting('dashboard_shell', 'modern'),
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
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_rut' => ['nullable', 'string', 'max:20'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:20'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'layout_template' => ['nullable', 'string', 'in:modern,sidebar,minimal,dark'],
            'dashboard_shell' => ['nullable', 'string', 'in:classic,modern,minimal,dark'],
        ]);

        $tenant = $this->currentTenant->get();
        $tenant->setSetting('brand_color', $validated['brand_color']);
        $tenant->setSetting('company_name', $validated['company_name']);
        $tenant->setSetting('company_rut', $validated['company_rut']);
        $tenant->setSetting('company_address', $validated['company_address']);
        $tenant->setSetting('company_phone', $validated['company_phone']);
        $tenant->setSetting('company_email', $validated['company_email']);

        // Update layout template directly on tenant model
        if (isset($validated['layout_template'])) {
            $tenant->layout_template = $validated['layout_template'];
        }

        // Update dashboard shell preference
        if (isset($validated['dashboard_shell'])) {
            $tenant->setSetting('dashboard_shell', $validated['dashboard_shell']);
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('tenants/' . $tenant->id . '/logo', 'public');
            $tenant->setSetting('logo', '/storage/' . $path);
        }

        $tenant->save();

        return back()->with('success', 'Configuración actualizada correctamente.');
    }
}

