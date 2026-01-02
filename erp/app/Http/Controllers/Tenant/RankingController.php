<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
    private const IVA_RATE = 0.19;
    private const IVA_DIVISOR = 1.19;

    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant->get();
        $selectedMonth = $request->get('month', date('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $selectedMonth = date('Y-m');
        }

        $monthStart = Carbon::parse($selectedMonth . '-01')->startOfMonth()->toDateString();
        $monthEnd = Carbon::parse($selectedMonth . '-01')->endOfMonth()->toDateString();
        $prevMonthStart = Carbon::parse($selectedMonth . '-01')->subMonth()->startOfMonth()->toDateString();
        $prevMonthEnd = Carbon::parse($selectedMonth . '-01')->subMonth()->endOfMonth()->toDateString();

        $isCurrentMonth = $selectedMonth === date('Y-m');
        $today = date('Y-m-d');
        $endDate = $isCurrentMonth ? $today : $monthEnd;

        // === KPIs ===

        // Products with/without sales (global)
        $totalProducts = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('is_archived', false)
            ->count();

        $productsWithSales = DB::table('sale_items')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('products.tenant_id', $tenant->id)
            ->distinct('sale_items.product_id')
            ->count('sale_items.product_id');

        $productsWithoutSales = $totalProducts - $productsWithSales;

        // Most sold/profitable of month
        $mostSoldMonth = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('products.tenant_id', $tenant->id)
            ->where('products.is_archived', false)
            ->whereBetween(DB::raw('DATE(sales.created_at)'), [$monthStart, $endDate])
            ->select('products.name', DB::raw('SUM(sale_items.quantity) as units_sold'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('units_sold')
            ->first();

        $mostProfitableMonth = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('products.tenant_id', $tenant->id)
            ->where('products.is_archived', false)
            ->whereBetween(DB::raw('DATE(sales.created_at)'), [$monthStart, $endDate])
            ->select('products.name', DB::raw('SUM(sale_items.quantity * ((sale_items.unit_price / 1.19) - products.cost)) as total_margin'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_margin')
            ->first();

        // Most sold/profitable of day (only if current month)
        $mostSoldDay = null;
        $mostProfitableDay = null;
        if ($isCurrentMonth) {
            $mostSoldDay = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->where('products.tenant_id', $tenant->id)
                ->whereDate('sales.created_at', $today)
                ->select('products.name', DB::raw('SUM(sale_items.quantity) as units_sold'))
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('units_sold')
                ->first();

            $mostProfitableDay = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->where('products.tenant_id', $tenant->id)
                ->whereDate('sales.created_at', $today)
                ->select('products.name', DB::raw('SUM(sale_items.quantity * ((sale_items.unit_price / 1.19) - products.cost)) as total_margin'))
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total_margin')
                ->first();
        }

        // === Ranking Tables ===

        $rankingGlobal = $this->getRankingData($tenant->id, null, null);
        $rankingMonthly = $this->getRankingData($tenant->id, $monthStart, $endDate);
        $rankingDaily = $isCurrentMonth ? $this->getRankingData($tenant->id, $today, $today) : [];

        // Products without sales (month)
        $unsoldMonthly = DB::table('products')
            ->leftJoin('sale_items', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('sales', function ($join) use ($monthStart, $monthEnd) {
                $join->on('sales.id', '=', 'sale_items.sale_id')
                    ->whereBetween(DB::raw('DATE(sales.created_at)'), [$monthStart, $monthEnd]);
            })
            ->where('products.tenant_id', $tenant->id)
            ->where('products.is_archived', false)
            ->whereNull('sales.id')
            ->select('products.id', 'products.name as product_name', 'products.stock')
            ->groupBy('products.id', 'products.name', 'products.stock')
            ->orderBy('products.name')
            ->get();

        // Products without sales (global)
        $unsoldGlobal = DB::table('products')
            ->leftJoin('sale_items', 'products.id', '=', 'sale_items.product_id')
            ->where('products.tenant_id', $tenant->id)
            ->where('products.is_archived', false)
            ->whereNull('sale_items.id')
            ->select('products.id', 'products.name as product_name', 'products.stock')
            ->groupBy('products.id', 'products.name', 'products.stock')
            ->orderBy('products.name')
            ->get();

        // Calculate totals for chart
        $totalMonthlyRevenue = collect($rankingMonthly)->sum('total_revenue');
        $totalMonthlyMargin = collect($rankingMonthly)->sum('total_margin');

        // Add percentage shares
        $rankingMonthly = collect($rankingMonthly)->map(function ($item) use ($totalMonthlyRevenue, $totalMonthlyMargin) {
            $item->revenue_share_pct = $totalMonthlyRevenue > 0 ? ($item->total_revenue / $totalMonthlyRevenue) * 100 : 0;
            $item->margin_share_pct = $totalMonthlyMargin > 0 ? ($item->total_margin / $totalMonthlyMargin) * 100 : 0;
            return $item;
        })->values();

        // Top 10 for chart (by revenue)
        $chartData = $rankingMonthly->sortByDesc('total_revenue')->take(10)->values();

        // Month options
        $monthOptions = [];
        $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        for ($i = 0; $i < 12; $i++) {
            $date = Carbon::now()->subMonths($i);
            $monthOptions[] = [
                'value' => $date->format('Y-m'),
                'label' => $months[$date->month - 1] . ' ' . $date->year,
            ];
        }

        return Inertia::render('Tenant/Ranking/Index', [
            'kpis' => [
                'total_products' => $totalProducts,
                'products_with_sales' => $productsWithSales,
                'products_without_sales' => $productsWithoutSales,
                'most_sold_month' => $mostSoldMonth,
                'most_profitable_month' => $mostProfitableMonth,
                'most_sold_day' => $mostSoldDay,
                'most_profitable_day' => $mostProfitableDay,
            ],
            'rankingGlobal' => $rankingGlobal,
            'rankingMonthly' => $rankingMonthly,
            'rankingDaily' => $rankingDaily,
            'unsoldMonthly' => $unsoldMonthly,
            'unsoldGlobal' => $unsoldGlobal,
            'chartData' => $chartData,
            'selectedMonth' => $selectedMonth,
            'monthOptions' => $monthOptions,
            'isCurrentMonth' => $isCurrentMonth,
        ]);
    }

    private function getRankingData(int $tenantId, ?string $startDate, ?string $endDate)
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'products.supplier_id')
            ->where('products.tenant_id', $tenantId)
            ->where('products.is_archived', false)
            ->select(
                'products.id',
                'products.name as product_name',
                'products.stock',
                DB::raw('COALESCE(suppliers.name, "N/A") as supplier_name'),
                DB::raw('SUM(sale_items.quantity) as units_sold'),
                DB::raw('SUM(sale_items.quantity * sale_items.unit_price) as total_revenue'),
                DB::raw('SUM(sale_items.quantity * ((sale_items.unit_price / 1.19) - products.cost)) as total_margin'),
                DB::raw('AVG((sale_items.unit_price / 1.19) - products.cost) as avg_unit_margin')
            )
            ->groupBy('products.id', 'products.name', 'products.stock', 'suppliers.name');

        if ($startDate && $endDate) {
            $query->whereBetween(DB::raw('DATE(sales.created_at)'), [$startDate, $endDate]);
        }

        return $query->orderByDesc('total_revenue')->get();
    }
}
