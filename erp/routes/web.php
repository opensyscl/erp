<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\TenantController;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

$appHost = parse_url(config('app.url'), PHP_URL_HOST);

if ($appHost) {
    Route::domain($appHost)->group(function () {
        Route::get('/', function () {
            return Inertia::render('Welcome', [
                'canLogin' => Route::has('login'),
                'canRegister' => Route::has('register'),
                'laravelVersion' => Application::VERSION,
                'phpVersion' => PHP_VERSION,
            ]);
        });
    });
}

/*
|--------------------------------------------------------------------------
| Authenticated Routes (Redirect based on role)
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
    $user = auth()->user();

    if ($user->isSuperAdmin()) {
        return redirect()->route('superadmin.dashboard');
    }

    if ($user->tenant) {
        // If current host matches tenant domain, use domain route
        if ($user->tenant->domain && $request->getHost() === $user->tenant->domain) {
             return redirect()->route('tenant.domain.dashboard', ['domain' => $user->tenant->domain]);
        }

        // Otherwise fall back to App Path route
        return redirect()->route('tenant.dashboard', ['tenant' => $user->tenant->slug]);
    }

    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| SuperAdmin Routes
|--------------------------------------------------------------------------
*/

Route::prefix('superadmin')
    ->middleware(['auth', 'verified', EnsureSuperAdmin::class])
    ->name('superadmin.')
    ->group(function () {
        Route::get('/', [SuperAdminDashboardController::class, 'index'])->name('dashboard');

        // Tenants CRUD
        Route::resource('tenants', TenantController::class);
        Route::post('tenants/{tenant}/toggle-status', [TenantController::class, 'toggleStatus'])
            ->name('tenants.toggle-status');
    });

/*
|--------------------------------------------------------------------------
| Tenant Routes (Future)
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Tenant Routes Definition
|--------------------------------------------------------------------------
*/

$tenantRoutes = function () {
    Route::get('/', function () {
        return Inertia::render('Tenant/Dashboard');
    })->name('dashboard');

    // Inventory Module
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\ProductController::class, 'index'])->name('index');
        Route::get('/analysis', [App\Http\Controllers\Tenant\InventoryAnalysisController::class, 'index'])->name('analysis');
        Route::get('/products/create', [App\Http\Controllers\Tenant\ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [App\Http\Controllers\Tenant\ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [App\Http\Controllers\Tenant\ProductController::class, 'edit'])->name('products.edit');
        Route::patch('/products/{product}', [App\Http\Controllers\Tenant\ProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product}/archive', [App\Http\Controllers\Tenant\ProductController::class, 'archive'])->name('products.archive');
        Route::delete('/products/{product}', [App\Http\Controllers\Tenant\ProductController::class, 'destroy'])->name('products.destroy');
        Route::patch('/products/{product}/stock', [App\Http\Controllers\Tenant\ProductController::class, 'updateStock'])->name('products.stock');
        Route::patch('/products/{product}/restore', [App\Http\Controllers\Tenant\ProductController::class, 'restore'])->name('products.restore');
        Route::post('/products/{product}/duplicate', [App\Http\Controllers\Tenant\ProductController::class, 'duplicate'])->name('products.duplicate');

        // Categories
        Route::get('/categories', [App\Http\Controllers\Tenant\CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [App\Http\Controllers\Tenant\CategoryController::class, 'store'])->name('categories.store');
        Route::patch('/categories/{category}', [App\Http\Controllers\Tenant\CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [App\Http\Controllers\Tenant\CategoryController::class, 'destroy'])->name('categories.destroy');

        // Suppliers
        Route::get('/suppliers', [App\Http\Controllers\Tenant\SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/create', [App\Http\Controllers\Tenant\SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/suppliers', [App\Http\Controllers\Tenant\SupplierController::class, 'store'])->name('suppliers.store');
        Route::patch('/suppliers/{supplier}', [App\Http\Controllers\Tenant\SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [App\Http\Controllers\Tenant\SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    // Settings
    Route::get('/settings', [App\Http\Controllers\Tenant\SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings', [App\Http\Controllers\Tenant\SettingsController::class, 'update'])->name('settings.update');
};

/*
|--------------------------------------------------------------------------
| Tenant Routes Registration
|--------------------------------------------------------------------------
*/

// 1. Path Based (Development / No Domain)
Route::prefix('app/{tenant}')
    ->middleware(['auth', 'verified', IdentifyTenant::class])
    ->name('tenant.')
    ->group($tenantRoutes);

// 2. Domain Based (Production) - Excludes main App URL
// Note: We DON'T use {domain} capture here to avoid parameter injection into controllers
$appHost = parse_url(config('app.url'), PHP_URL_HOST);
if ($appHost) {
    // Get all tenant domains from database (cached)
    $tenantDomains = cache()->remember('tenant_domains', 3600, function () {
        return \App\Models\Tenant::whereNotNull('domain')->pluck('domain')->toArray();
    });

    foreach ($tenantDomains as $tenantDomain) {
        if ($tenantDomain && $tenantDomain !== $appHost) {
            Route::domain($tenantDomain)
                ->middleware(['auth', 'verified', IdentifyTenant::class])
                ->name("tenant.domain.{$tenantDomain}.")
                ->group($tenantRoutes);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Profile Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
