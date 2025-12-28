<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the superadmin dashboard.
     */
    public function index(): Response
    {
        $stats = [
            'totalTenants' => Tenant::count(),
            'activeTenants' => Tenant::where('status', 'active')->count(),
            'suspendedTenants' => Tenant::where('status', 'suspended')->count(),
            'totalUsers' => User::whereNotNull('tenant_id')->count(),
            'superAdmins' => User::whereNull('tenant_id')->count(),
        ];

        $recentTenants = Tenant::latest()
            ->take(5)
            ->get(['id', 'name', 'slug', 'status', 'plan', 'created_at']);

        return Inertia::render('SuperAdmin/Dashboard', [
            'stats' => $stats,
            'recentTenants' => $recentTenants,
        ]);
    }
}
