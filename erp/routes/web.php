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


// Public quotation view (no auth required)
Route::get('/quotations/view/{id}', [App\Http\Controllers\PublicQuotationController::class, 'show'])->name('quotations.public.show');

// Public order view (no auth required)
Route::get('/orders/view/{id}', [App\Http\Controllers\PublicOrderController::class, 'show'])->name('orders.public.show');

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
        // If current host matches tenant domain, just redirect to root (it's the tenant dashboard)
        if ($user->tenant->domain && $request->getHost() === $user->tenant->domain) {
             return redirect('/');
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
        $tenant = app(\App\Services\CurrentTenant::class)->get();
        return Inertia::render('Tenant/Dashboard/Index', [
            'settings' => [
                'dashboard_shell' => $tenant?->getSetting('dashboard_shell', 'classic'),
                'layout_template' => $tenant?->layout_template ?? 'modern',
            ],
        ]);
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

    // Purchase Invoices Module
    Route::prefix('purchases')->name('purchases.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\PurchaseInvoiceController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Tenant\PurchaseInvoiceController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Tenant\PurchaseInvoiceController::class, 'store'])->name('store');
        Route::get('/{purchase}', [App\Http\Controllers\Tenant\PurchaseInvoiceController::class, 'show'])->name('show');
        Route::patch('/{purchase}/pay', [App\Http\Controllers\Tenant\PurchaseInvoiceController::class, 'markAsPaid'])->name('pay');
        Route::delete('/{purchase}', [App\Http\Controllers\Tenant\PurchaseInvoiceController::class, 'destroy'])->name('destroy');
    });

    // POS (Point of Sale) Module
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\POSController::class, 'index'])->name('index');
        Route::post('/sale', [App\Http\Controllers\Tenant\POSController::class, 'store'])->name('store');
        Route::get('/lookup-receipt/{receipt}', [App\Http\Controllers\Tenant\POSController::class, 'getSaleByReceipt'])->name('sale.receipt');
        Route::get('/sale/{sale}', [App\Http\Controllers\Tenant\POSController::class, 'getSale'])->name('sale.show');
        Route::post('/refund', [App\Http\Controllers\Tenant\POSController::class, 'refund'])->name('refund');
    });

    // Sales Report Module
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\SalesReportController::class, 'index'])->name('index');
        Route::get('/{sale}', [App\Http\Controllers\Tenant\SaleController::class, 'show'])->name('show');
    });

    // Schedules Module
    Route::prefix('schedules')->name('schedules.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\ScheduleController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Tenant\ScheduleController::class, 'store'])->name('store');
        Route::delete('/{schedule}', [App\Http\Controllers\Tenant\ScheduleController::class, 'destroy'])->name('destroy');
    });

    // Expenses Module (Gastos Operativos)
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\ExpensesController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Tenant\ExpensesController::class, 'store'])->name('store');
    });

    // Attendance Module (Registro de Asistencias)
    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\AttendanceController::class, 'index'])->name('index');
        Route::post('/check-rut', [App\Http\Controllers\Tenant\AttendanceController::class, 'checkRut'])->name('check-rut');
        Route::post('/check-pin', [App\Http\Controllers\Tenant\AttendanceController::class, 'checkPin'])->name('check-pin');
        Route::post('/register-event', [App\Http\Controllers\Tenant\AttendanceController::class, 'registerEvent'])->name('register-event');
    });

    // Ranking Module (Ranking de Productos)
    Route::get('/ranking', [App\Http\Controllers\Tenant\RankingController::class, 'index'])->name('ranking.index');

    // Offers Module (Ofertas y Packs)
    Route::prefix('offers')->name('offers.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\OffersController::class, 'index'])->name('index');
        Route::get('/search', [App\Http\Controllers\Tenant\OffersController::class, 'searchProducts'])->name('search');
        Route::get('/{id}', [App\Http\Controllers\Tenant\OffersController::class, 'show'])->name('show');
        Route::post('/', [App\Http\Controllers\Tenant\OffersController::class, 'store'])->name('store');
        Route::put('/{id}', [App\Http\Controllers\Tenant\OffersController::class, 'update'])->name('update');
        Route::delete('/{id}', [App\Http\Controllers\Tenant\OffersController::class, 'destroy'])->name('destroy');
    });

    // Labels Module (Centro de Etiquetas)
    Route::get('/labels', [App\Http\Controllers\Tenant\LabelsController::class, 'index'])->name('labels.index');

    // Quotations Module (Cotizaciones)
    Route::prefix('quotations')->name('quotations.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\QuotationsController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Tenant\QuotationsController::class, 'store'])->name('store');
        Route::get('/{id}', [App\Http\Controllers\Tenant\QuotationsController::class, 'show'])->name('show');
    });

    // Orders Module (Órdenes de Compra)
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\OrdersController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Tenant\OrdersController::class, 'store'])->name('store');
        Route::get('/{id}', [App\Http\Controllers\Tenant\OrdersController::class, 'show'])->name('show');
    });

    // Terceros Module (Clientes y Proveedores)
    Route::prefix('terceros')->name('terceros.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\TercerosController::class, 'index'])->name('index');
        Route::post('/clients', [App\Http\Controllers\Tenant\TercerosController::class, 'storeClient'])->name('clients.store');
        Route::put('/clients/{id}', [App\Http\Controllers\Tenant\TercerosController::class, 'updateClient'])->name('clients.update');
        Route::post('/suppliers', [App\Http\Controllers\Tenant\TercerosController::class, 'storeSupplier'])->name('suppliers.store');
        Route::put('/suppliers/{id}', [App\Http\Controllers\Tenant\TercerosController::class, 'updateSupplier'])->name('suppliers.update');
    });

    // Capital Module (Análisis de Utilidad y Reinversión)
    Route::prefix('capital')->name('capital.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\CapitalController::class, 'index'])->name('index');
        Route::get('/suppliers', [App\Http\Controllers\Tenant\CapitalController::class, 'bySupplier'])->name('suppliers');
    });

    // Cash Closings Module (Cuadres de Caja)
    Route::prefix('cash-closings')->name('cash-closings.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\CashClosingController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Tenant\CashClosingController::class, 'store'])->name('store');
    });

    // Supplier Payments Module
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\SupplierPaymentController::class, 'index'])->name('index');
        Route::post('/{id}/pay', [App\Http\Controllers\Tenant\SupplierPaymentController::class, 'markAsPaid'])->name('pay');
        Route::post('/{id}/undo', [App\Http\Controllers\Tenant\SupplierPaymentController::class, 'undoPayment'])->name('undo');
        Route::put('/{id}/amount', [App\Http\Controllers\Tenant\SupplierPaymentController::class, 'updateAmount'])->name('update-amount');
    });

    // Task Center (Centro de Tareas)
    Route::prefix('tasks')->name('tasks.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\TaskController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Tenant\TaskController::class, 'store'])->name('store');
        Route::put('/{task}', [App\Http\Controllers\Tenant\TaskController::class, 'update'])->name('update');
        Route::delete('/{task}', [App\Http\Controllers\Tenant\TaskController::class, 'destroy'])->name('destroy');
        Route::get('/metrics', [App\Http\Controllers\Tenant\TaskController::class, 'metrics'])->name('metrics');
    });

    // Internal Consumption (Consumo Interno)
    Route::prefix('outputs')->name('outputs.')->group(function () {
        Route::get('/search', [App\Http\Controllers\Tenant\InternalConsumptionController::class, 'search'])->name('search');
        Route::get('/', [App\Http\Controllers\Tenant\InternalConsumptionController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Tenant\InternalConsumptionController::class, 'store'])->name('store');
        Route::put('/{output}', [App\Http\Controllers\Tenant\InternalConsumptionController::class, 'update'])->name('update');
        Route::delete('/{output}', [App\Http\Controllers\Tenant\InternalConsumptionController::class, 'destroy'])->name('destroy');
    });

    // Decrease (Mermas)
    Route::prefix('decrease')->name('decrease.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\DecreaseController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Tenant\DecreaseController::class, 'store'])->name('store');
        Route::delete('/{decrease}', [App\Http\Controllers\Tenant\DecreaseController::class, 'destroy'])->name('destroy');
    });

    // Shop (Catálogo)
    Route::get('/shop', [App\Http\Controllers\Tenant\ShopController::class, 'index'])->name('shop.index');

    // Employees API
    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\EmployeeController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Tenant\EmployeeController::class, 'store'])->name('store');
        Route::put('/{employee}', [App\Http\Controllers\Tenant\EmployeeController::class, 'update'])->name('update');
        Route::delete('/{employee}', [App\Http\Controllers\Tenant\EmployeeController::class, 'destroy'])->name('destroy');
    });

    // Shifts API
    Route::prefix('shifts')->name('shifts.')->group(function () {
        Route::get('/', [App\Http\Controllers\Tenant\ShiftController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Tenant\ShiftController::class, 'store'])->name('store');
        Route::put('/{shift}', [App\Http\Controllers\Tenant\ShiftController::class, 'update'])->name('update');
        Route::delete('/{shift}', [App\Http\Controllers\Tenant\ShiftController::class, 'destroy'])->name('destroy');
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
if ($appHost && !app()->runningInConsole()) {
    // Only load tenant domains when not running artisan commands
    try {
        // Get all tenant domains from database (cached)
        $tenantDomains = cache()->remember('tenant_domains', 3600, function () {
            return \App\Models\Tenant::whereNotNull('domain')->pluck('domain')->toArray();
        });

        foreach ($tenantDomains as $tenantDomain) {
            // ALLOW current appHost to be a tenant domain for local dev/single tenant usage
            if ($tenantDomain) {
                Route::domain($tenantDomain)
                    ->middleware(['auth', 'verified', IdentifyTenant::class])
                    ->name("tenant.domain.{$tenantDomain}.")
                    ->group($tenantRoutes);
            }
        }
    } catch (\Exception $e) {
        // Silently fail if database is not available (e.g., during migrations)
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
