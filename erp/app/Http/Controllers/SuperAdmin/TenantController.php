<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    /**
     * Display a listing of tenants.
     */
    public function index(Request $request): Response
    {
        $tenants = Tenant::query()
            ->withCount('users')
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->search}%")
                    ->orWhere('slug', 'like', "%{$request->search}%");
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->filled('plan'), function ($query) use ($request) {
                $query->where('plan', $request->plan);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('SuperAdmin/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => $request->only(['search', 'status', 'plan']),
        ]);
    }

    /**
     * Show the form for creating a new tenant.
     */
    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Tenants/Create');
    }

    /**
     * Store a newly created tenant.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants', 'alpha_dash'],
            'plan' => ['required', 'string', 'in:free,basic,pro,enterprise'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'unique:users,email'],
            'admin_password' => ['required', Password::defaults()],
        ]);

        // Create tenant
        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'status' => 'active',
            'plan' => $validated['plan'],
        ]);

        // Create admin user for tenant
        $user = User::create([
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => Hash::make($validated['admin_password']),
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('tenant_admin');

        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Tienda '{$tenant->name}' creada exitosamente.");
    }

    /**
     * Show the form for editing the specified tenant.
     */
    public function edit(Tenant $tenant): Response
    {
        return Inertia::render('SuperAdmin/Tenants/Edit', [
            'tenant' => $tenant,
        ]);
    }

    /**
     * Update the specified tenant.
     */
    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug,' . $tenant->id, 'alpha_dash'],
            'status' => ['required', 'string', 'in:active,inactive,suspended'],
            'plan' => ['required', 'string', 'in:free,basic,pro,enterprise'],
        ]);

        $tenant->update($validated);

        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Tienda '{$tenant->name}' actualizada.");
    }

    /**
     * Remove the specified tenant.
     */
    public function destroy(Tenant $tenant)
    {
        $name = $tenant->name;
        $tenant->delete();

        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Tienda '{$name}' eliminada.");
    }

    /**
     * Toggle tenant status.
     */
    public function toggleStatus(Tenant $tenant)
    {
        $newStatus = $tenant->status === 'active' ? 'suspended' : 'active';
        $tenant->update(['status' => $newStatus]);

        $message = $newStatus === 'active'
            ? "Tienda '{$tenant->name}' activada."
            : "Tienda '{$tenant->name}' suspendida.";

        return back()->with('success', $message);
    }
}
