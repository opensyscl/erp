<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardWidgetController extends Controller
{
    /**
     * Get revenue data for the revenue chart widget.
     */
    public function revenue(): JsonResponse
    {
        $now = Carbon::now();

        // Today's data - hourly breakdown
        $todayStart = $now->copy()->startOfDay();
        $todayData = [];
        for ($hour = 0; $hour < 24; $hour += 2) {
            $startHour = $todayStart->copy()->addHours($hour);
            $endHour = $startHour->copy()->addHours(2);
            $total = Sale::completed()
                ->whereBetween('created_at', [$startHour, $endHour])
                ->sum('total');
            $todayData[] = (int) round($total / 1000); // Convert to thousands
        }

        // Week data - daily breakdown
        $weekStart = $now->copy()->startOfWeek();
        $weekData = [];
        for ($day = 0; $day < 7; $day++) {
            $dayStart = $weekStart->copy()->addDays($day)->startOfDay();
            $dayEnd = $dayStart->copy()->endOfDay();
            $total = Sale::completed()
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->sum('total');
            $weekData[] = (int) round($total / 1000);
        }

        // Month data - monthly breakdown for the year
        $yearStart = $now->copy()->startOfYear();
        $monthData = [];
        for ($month = 0; $month < 12; $month++) {
            $monthStart = $yearStart->copy()->addMonths($month)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $total = Sale::completed()
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('total');
            $monthData[] = (int) round($total / 1000);
        }

        // Calculate totals and percent change
        $todayTotal = Sale::completed()
            ->whereDate('created_at', $now->toDateString())
            ->sum('total');

        $lastMonthTotal = Sale::completed()
            ->whereBetween('created_at', [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth()
            ])
            ->sum('total');

        $currentMonthTotal = Sale::completed()
            ->whereBetween('created_at', [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth()
            ])
            ->sum('total');

        $percentChange = $lastMonthTotal > 0
            ? round((($currentMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100)
            : 0;

        return response()->json([
            'todayData' => $todayData,
            'weekData' => $weekData,
            'monthData' => $monthData,
            'totalRevenue' => (float) $currentMonthTotal,
            'percentChange' => (int) $percentChange,
            'todayTotal' => (float) $todayTotal,
        ]);
    }

    /**
     * Get quick stats for the dashboard.
     */
    public function stats(): JsonResponse
    {
        $now = Carbon::now();

        // Today's sales
        $todaySales = Sale::completed()
            ->whereDate('created_at', $now->toDateString())
            ->sum('total');

        $todayTransactions = Sale::completed()
            ->whereDate('created_at', $now->toDateString())
            ->count();

        // Average ticket
        $averageTicket = $todayTransactions > 0
            ? $todaySales / $todayTransactions
            : 0;

        // Products count
        $productsCount = Product::where('is_active', true)->count();

        // Low stock products
        $lowStockCount = Product::where('is_active', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->count();

        // Last sale
        $lastSale = Sale::completed()
            ->latest()
            ->first();

        return response()->json([
            'todaySales' => (float) $todaySales,
            'todayTransactions' => $todayTransactions,
            'averageTicket' => round((float) $averageTicket, 2),
            'productsCount' => $productsCount,
            'lowStockCount' => $lowStockCount,
            'lastSale' => $lastSale ? [
                'total' => (float) $lastSale->total,
                'time' => $lastSale->created_at->diffForHumans(),
            ] : null,
        ]);
    }

    /**
     * Get top products sold for the widget.
     */
    public function topProducts(string $tenant): JsonResponse
    {
        $now = Carbon::now();
        $currentTenant = app(\App\Services\CurrentTenant::class);
        $tenantId = $currentTenant->id();

        // Get top 5 products from the current week
        $topProducts = \DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek()
            ])
            ->select(
                'products.id',
                'products.name',
                \DB::raw('SUM(sale_items.quantity) as total_quantity'),
                \DB::raw('SUM(sale_items.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        // Calculate total for percentage
        $totalRevenue = $topProducts->sum('total_revenue');

        // Format data for chart
        $products = $topProducts->map(function ($product) use ($totalRevenue) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'quantity' => (int) $product->total_quantity,
                'revenue' => (float) $product->total_revenue,
                'percentage' => $totalRevenue > 0
                    ? round(($product->total_revenue / $totalRevenue) * 100, 1)
                    : 0,
            ];
        })->values()->all();

        return response()->json([
            'products' => $products,
            'totalRevenue' => (float) $totalRevenue,
            'period' => 'Esta semana',
        ]);
    }

    /**
     * Get stock alerts data for the widget.
     */
    public function stockAlerts(): JsonResponse
    {
        // Get current tenant
        $currentTenant = app(\App\Services\CurrentTenant::class);
        $tenantId = $currentTenant->check() ? $currentTenant->id() : null;

        // Base query - bypassed scope for now until middleware is fixed
        $baseQuery = Product::withoutTenantScope()
            ->where('is_active', true)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        // Calculate ranges
        // < 10
        $range10 = (clone $baseQuery)->where('stock', '<', 10)->count();

        // 10 - 20
        $range20 = (clone $baseQuery)->where('stock', '>=', 10)->where('stock', '<', 20)->count();

        // 20 - 30
        $range30 = (clone $baseQuery)->where('stock', '>=', 20)->where('stock', '<', 30)->count();

        // 30 - 50
        $range50 = (clone $baseQuery)->where('stock', '>=', 30)->where('stock', '<', 50)->count();

        // 50+
        $rangeOver50 = (clone $baseQuery)->where('stock', '>=', 50)->count();

        $total = $range10 + $range20 + $range30 + $range50 + $rangeOver50;
        $alertCount = $range10 + $range20 + $range30 + $range50;

        return response()->json([
            'categories' => [
                [
                    'name' => 'Menos de 10',
                    'count' => $range10,
                    'percentage' => $total > 0 ? round(($range10 / $total) * 100, 1) : 0,
                    'color' => '#ef4444', // red
                    'severity' => 'critical',
                    'slug' => 'range-10',
                ],
                [
                    'name' => 'Menos de 20',
                    'count' => $range20,
                    'percentage' => $total > 0 ? round(($range20 / $total) * 100, 1) : 0,
                    'color' => '#f97316', // orange
                    'severity' => 'warning',
                    'slug' => 'range-20',
                ],
                [
                    'name' => 'Menos de 30',
                    'count' => $range30,
                    'percentage' => $total > 0 ? round(($range30 / $total) * 100, 1) : 0,
                    'color' => '#eab308', // yellow
                    'severity' => 'caution',
                    'slug' => 'range-30',
                ],
                [
                    'name' => 'Menos de 50',
                    'count' => $range50,
                    'percentage' => $total > 0 ? round(($range50 / $total) * 100, 1) : 0,
                    'color' => '#3b82f6', // blue
                    'severity' => 'info',
                    'slug' => 'range-50',
                ],
            ],
            'total' => $total,
            'alertCount' => $alertCount,
        ]);
    }

    /**
     * Get products by stock category for the drawer.
     */
    public function stockAlertProducts(string $tenant, string $category): JsonResponse
    {
        // Get current tenant
        $currentTenant = app(\App\Services\CurrentTenant::class);
        $tenantId = $currentTenant->check() ? $currentTenant->id() : null;

        // Base query
        $query = Product::withoutTenantScope()
            ->where('is_active', true)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        switch ($category) {
            // New Range Slugs
            case 'range-10':
                $query->where('stock', '<', 10);
                break;
            case 'range-20':
                $query->where('stock', '>=', 10)->where('stock', '<', 20);
                break;
            case 'range-30':
                $query->where('stock', '>=', 20)->where('stock', '<', 30);
                break;
            case 'range-50':
                $query->where('stock', '>=', 30)->where('stock', '<', 50);
                break;

            // Legacy / Fallback Slugs (Map to closest range or specific logic)
            case 'out-of-stock':
                $query->where('stock', '<', 10); // Map to range-10
                break;
            case 'critical':
                $query->where('stock', '<', 10); // Map to range-10
                break;
            case 'low':
                $query->where('stock', '>=', 10)->where('stock', '<', 30); // Map to ~range-20/30
                break;
            case 'normal':
                $query->where('stock', '>=', 30); // Map to range-50+
                break;

            default:
                // Handle legacy slugs fallback or invalid
                return response()->json(['products' => [], 'error' => 'Invalid category: ' . $category]);
        }

        $products = $query->select('id', 'name', 'sku', 'stock', 'min_stock', 'price', 'image')
            ->orderBy('stock', 'asc')
            ->limit(50)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'stock' => $p->stock,
                'minStock' => $p->min_stock,
                'price' => (float) $p->price,
                'image' => $p->image,
            ]);

        return response()->json([
            'products' => $products,
            'category' => $category,
        ]);
    }

    /**
     * Get recent sales for the timeline widget.
     */
    public function recentSales(string $tenant): JsonResponse
    {
        $currentTenant = app(\App\Services\CurrentTenant::class);
        $tenantId = $currentTenant->check() ? $currentTenant->id() : null;

        // Build base query - bypass global scope and filter manually
        $query = Sale::withoutGlobalScopes()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        // Get latest 10 sales
        $sales = $query
            ->with(['items'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'code' => $sale->sale_number ?? '#' . $sale->id,
                    'total' => (float) $sale->total,
                    'status' => $sale->status,
                    'itemsCount' => $sale->items->count(),
                    'customer' => null, // Customer model doesn't exist yet
                    'createdAt' => $sale->created_at->toIso8601String(),
                    'timeAgo' => $sale->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'sales' => $sales,
        ]);
    }

    /**
     * Get sales breakdown by category for doughnut chart.
     */
    public function salesByCategory(string $tenant): JsonResponse
    {
        $currentTenant = app(\App\Services\CurrentTenant::class);
        $tenantId = $currentTenant->check() ? $currentTenant->id() : null;

        $now = Carbon::now();

        // Get sales by category for current month
        $salesByCategory = \DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('sales.status', 'completed')
            ->when($tenantId, fn($q) => $q->where('sales.tenant_id', $tenantId))
            ->whereBetween('sales.created_at', [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth()
            ])
            ->select(
                'categories.id',
                'categories.name',
                \DB::raw('SUM(sale_items.subtotal) as total_revenue'),
                \DB::raw('SUM(sale_items.quantity) as total_quantity')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_revenue')
            ->limit(6) // Top 6 categories
            ->get();

        // Calculate total for percentages
        $totalRevenue = $salesByCategory->sum('total_revenue');

        // Prepare chart data
        $categories = $salesByCategory->map(function ($cat) use ($totalRevenue) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'revenue' => (float) $cat->total_revenue,
                'quantity' => (int) $cat->total_quantity,
                'percentage' => $totalRevenue > 0
                    ? round(($cat->total_revenue / $totalRevenue) * 100, 1)
                    : 0,
            ];
        })->values()->all();

        return response()->json([
            'categories' => $categories,
            'totalRevenue' => (float) $totalRevenue,
            'period' => 'month',
        ]);
    }

    /**
     * Get sales distribution by hour and day of week for heatmap.
     */
    public function salesByTime(string $tenant): JsonResponse
    {
        $currentTenant = app(\App\Services\CurrentTenant::class);
        $tenantId = $currentTenant->check() ? $currentTenant->id() : null;

        $now = Carbon::now();

        // Get sales grouped by day of week and hour for the last 4 weeks
        $salesData = \DB::table('sales')
            ->where('status', 'completed')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [
                $now->copy()->subWeeks(4)->startOfWeek(),
                $now->copy()->endOfWeek()
            ])
            ->select(
                \DB::raw('DAYOFWEEK(created_at) as day_of_week'),
                \DB::raw('HOUR(created_at) as hour'),
                \DB::raw('COUNT(*) as order_count'),
                \DB::raw('SUM(total) as total_revenue')
            )
            ->groupBy('day_of_week', 'hour')
            ->get();

        // Time blocks (2-hour intervals)
        $timeBlocks = [
            ['name' => '8am', 'start' => 8, 'end' => 10],
            ['name' => '10am', 'start' => 10, 'end' => 12],
            ['name' => '12pm', 'start' => 12, 'end' => 14],
            ['name' => '2pm', 'start' => 14, 'end' => 16],
            ['name' => '4pm', 'start' => 16, 'end' => 18],
            ['name' => '6pm', 'start' => 18, 'end' => 20],
        ];

        // Days of week (MySQL DAYOFWEEK: 1=Sunday, 2=Monday, etc)
        $daysMap = [2 => 0, 3 => 1, 4 => 2, 5 => 3, 6 => 4, 7 => 5, 1 => 6]; // Mon=0, Sun=6
        $dayNames = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

        // Build heatmap series data
        $series = [];
        foreach ($timeBlocks as $block) {
            $data = array_fill(0, 7, 0); // 7 days initialized to 0

            foreach ($salesData as $sale) {
                $hour = (int) $sale->hour;
                $dayIndex = $daysMap[$sale->day_of_week] ?? null;

                if ($dayIndex !== null && $hour >= $block['start'] && $hour < $block['end']) {
                    $data[$dayIndex] += (int) $sale->order_count;
                }
            }

            $series[] = [
                'name' => $block['name'],
                'data' => $data,
            ];
        }

        return response()->json([
            'series' => $series,
            'categories' => $dayNames,
        ]);
    }

    /**
     * Get profit margin data with revenue, costs, and order status breakdown.
     */
    public function profitMargin(string $tenant): JsonResponse
    {
        $currentTenant = app(\App\Services\CurrentTenant::class);
        $tenantId = $currentTenant->check() ? $currentTenant->id() : null;

        $now = Carbon::now();

        // Get current month sales data
        $salesData = Sale::withoutGlobalScopes()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth()
            ])
            ->selectRaw("
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) as refunded_orders,
                SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status = 'completed' THEN COALESCE(cost_of_goods_sold, 0) ELSE 0 END) as total_costs
            ")
            ->first();

        $revenue = (float) ($salesData->total_revenue ?? 0);
        $costs = (float) ($salesData->total_costs ?? 0);
        $profit = $revenue - $costs;
        $marginPercentage = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0;

        $totalOrders = (int) ($salesData->total_orders ?? 0);
        $completedOrders = (int) ($salesData->completed_orders ?? 0);
        $cancelledOrders = (int) ($salesData->cancelled_orders ?? 0);
        $refundedOrders = (int) ($salesData->refunded_orders ?? 0);

        // Calculate percentages for status
        $completedPct = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100) : 0;
        $cancelledPct = $totalOrders > 0 ? round(($cancelledOrders / $totalOrders) * 100) : 0;
        $refundedPct = $totalOrders > 0 ? round(($refundedOrders / $totalOrders) * 100) : 0;

        return response()->json([
            'revenue' => $revenue,
            'costs' => $costs,
            'profit' => $profit,
            'marginPercentage' => $marginPercentage,
            'totalOrders' => $totalOrders,
            'orderStatus' => [
                'completed' => [
                    'count' => $completedOrders,
                    'percentage' => $completedPct,
                ],
                'cancelled' => [
                    'count' => $cancelledOrders,
                    'percentage' => $cancelledPct,
                ],
                'refunded' => [
                    'count' => $refundedOrders,
                    'percentage' => $refundedPct,
                ],
            ],
            'period' => 'month',
        ]);
    }

    /**
     * Get cash register status for today.
     */
    public function cashStatus(string $tenant): JsonResponse
    {
        $currentTenant = app(\App\Services\CurrentTenant::class);
        $tenantId = $currentTenant->check() ? $currentTenant->id() : null;

        $today = Carbon::today();

        // Check if there's a closing for today (means cash is closed)
        $todayClosing = \App\Models\CashClosing::whereDate('closing_date', $today)->first();
        $isClosed = $todayClosing !== null;

        // Get yesterday's closing for starting cash
        $yesterdayClosing = \App\Models\CashClosing::whereDate('closing_date', $today->copy()->subDay())
            ->first();
        $startingCash = $yesterdayClosing ? (float) $yesterdayClosing->ending_cash : 0;

        // Get today's sales by payment method
        $todaySales = Sale::withoutGlobalScopes()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereDate('created_at', $today)
            ->where('status', 'completed')
            ->selectRaw("
                COUNT(*) as total_count,
                SUM(total) as total_amount,
                SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END) as cash_sales,
                SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END) as card_sales,
                SUM(CASE WHEN payment_method = 'transfer' THEN total ELSE 0 END) as transfer_sales,
                SUM(CASE WHEN payment_method NOT IN ('cash', 'card', 'transfer') THEN total ELSE 0 END) as other_sales
            ")
            ->first();

        $cashSales = (float) ($todaySales->cash_sales ?? 0);
        $cardSales = (float) ($todaySales->card_sales ?? 0);
        $transferSales = (float) ($todaySales->transfer_sales ?? 0);
        $otherSales = (float) ($todaySales->other_sales ?? 0);
        $totalSales = (float) ($todaySales->total_amount ?? 0);
        $salesCount = (int) ($todaySales->total_count ?? 0);

        // Calculate current cash balance (starting + cash sales only)
        $currentCash = $startingCash + $cashSales;

        // Get first sale time as "opened at" approximation
        $firstSale = Sale::withoutGlobalScopes()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereDate('created_at', $today)
            ->orderBy('created_at', 'asc')
            ->first();

        $openedAt = $firstSale ? $firstSale->created_at->format('H:i') : null;
        $openedAgo = $firstSale ? $firstSale->created_at->diffForHumans(['parts' => 2]) : null;

        return response()->json([
            'isOpen' => !$isClosed,
            'startingCash' => $startingCash,
            'currentCash' => $currentCash,
            'openedAt' => $openedAt,
            'openedAgo' => $openedAgo,
            'sales' => [
                'count' => $salesCount,
                'total' => $totalSales,
                'cash' => $cashSales,
                'card' => $cardSales,
                'transfer' => $transferSales,
                'other' => $otherSales,
            ],
            'closing' => $todayClosing ? [
                'endingCash' => (float) $todayClosing->ending_cash,
                'totalOutgoings' => (float) $todayClosing->total_outgoings,
            ] : null,
        ]);
    }

    /**
     * Get operational expenses for current month.
     */
    public function expenses(string $tenant): JsonResponse
    {
        $currentTenant = app(\App\Services\CurrentTenant::class);
        $tenantId = $currentTenant->check() ? $currentTenant->id() : null;

        $now = Carbon::now();

        // Get current month expenses
        $currentMonth = \App\Models\OperationalExpense::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereMonth('date_paid', $now->month)
            ->whereYear('date_paid', $now->year)
            ->selectRaw("
                SUM(total_amount) as total,
                SUM(COALESCE(light, 0)) as light,
                SUM(COALESCE(water, 0)) as water,
                SUM(COALESCE(rent, 0)) as rent,
                SUM(COALESCE(alarm, 0)) as alarm,
                SUM(COALESCE(internet, 0)) as internet,
                SUM(COALESCE(iva, 0)) as iva,
                SUM(COALESCE(repairs, 0)) as repairs,
                SUM(COALESCE(supplies, 0)) as supplies,
                SUM(COALESCE(other, 0)) as other
            ")
            ->first();

        // Get previous month expenses for comparison
        $prevMonth = \App\Models\OperationalExpense::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereMonth('date_paid', $now->copy()->subMonth()->month)
            ->whereYear('date_paid', $now->copy()->subMonth()->year)
            ->selectRaw('SUM(total_amount) as total')
            ->first();

        $currentTotal = (float) ($currentMonth->total ?? 0);
        $prevTotal = (float) ($prevMonth->total ?? 0);

        // Calculate change percentage
        $changePercent = $prevTotal > 0
            ? round((($currentTotal - $prevTotal) / $prevTotal) * 100, 1)
            : 0;

        // Build categories array with icons/labels
        $categories = [
            ['key' => 'rent', 'label' => 'Arriendo', 'icon' => '🏠', 'amount' => (float) ($currentMonth->rent ?? 0)],
            ['key' => 'light', 'label' => 'Luz', 'icon' => '🔌', 'amount' => (float) ($currentMonth->light ?? 0)],
            ['key' => 'water', 'label' => 'Agua', 'icon' => '💧', 'amount' => (float) ($currentMonth->water ?? 0)],
            ['key' => 'internet', 'label' => 'Internet', 'icon' => '🌐', 'amount' => (float) ($currentMonth->internet ?? 0)],
            ['key' => 'supplies', 'label' => 'Insumos', 'icon' => '📦', 'amount' => (float) ($currentMonth->supplies ?? 0)],
            ['key' => 'repairs', 'label' => 'Reparaciones', 'icon' => '🔧', 'amount' => (float) ($currentMonth->repairs ?? 0)],
            ['key' => 'alarm', 'label' => 'Alarma', 'icon' => '🚨', 'amount' => (float) ($currentMonth->alarm ?? 0)],
            ['key' => 'iva', 'label' => 'IVA', 'icon' => '📋', 'amount' => (float) ($currentMonth->iva ?? 0)],
            ['key' => 'other', 'label' => 'Otros', 'icon' => '📁', 'amount' => (float) ($currentMonth->other ?? 0)],
        ];

        // Sort by amount descending and filter out zeros
        $categories = collect($categories)
            ->filter(fn($c) => $c['amount'] > 0)
            ->sortByDesc('amount')
            ->values()
            ->all();

        // Find top expense
        $topExpense = !empty($categories) ? $categories[0] : null;

        return response()->json([
            'total' => $currentTotal,
            'previousTotal' => $prevTotal,
            'changePercent' => $changePercent,
            'categories' => $categories,
            'topExpense' => $topExpense,
            'period' => $now->format('F Y'),
        ]);
    }
}
