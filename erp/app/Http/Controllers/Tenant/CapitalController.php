<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Services\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class CapitalController extends Controller
{
    /**
     * IVA rate configuration (Chile 19%)
     */
    private const IVA_RATE = 1.19;
    private const IVA_FACTOR = 0.19;

    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display the Capital Analysis (Profit & Reinvestment) dashboard.
     */
    public function index(Request $request): Response
    {
        // Date range handling
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $month = $request->query('month');

        $isCustomRange = false;

        if ($startDate && $endDate) {
            $isCustomRange = true;
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
            $month = null;
        } elseif ($month) {
            $startDate = Carbon::parse($month . '-01')->startOfMonth();
            $endDate = Carbon::parse($month . '-01')->endOfMonth();
        } else {
            $month = now()->format('Y-m');
            $startDate = now()->startOfMonth();
            $endDate = now()->endOfMonth();
        }

        $daysInRange = $startDate->diffInDays($endDate) + 1;

        // --- MAIN KPIs ---

        // 1. Monthly Sales (Gross Revenue - includes IVA)
        $monthlySales = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->sum(DB::raw('sale_items.unit_price * sale_items.quantity'));

        // 2. Monthly Sales Net (Revenue without IVA)
        $monthlySalesNet = $monthlySales / self::IVA_RATE;

        // 3. IVA Collected (from sales)
        $monthlyIva = $monthlySales - $monthlySalesNet;

        // 4. Cost of Goods Sold (CMV)
        $monthlyCmv = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->sum(DB::raw('sale_items.unit_cost * sale_items.quantity'));

        // 5. Gross Profit (Net Sales - CMV)
        $monthlyProfit = $monthlySalesNet - $monthlyCmv;

        // 6. IVA Paid (from purchases - tax credit)
        $monthlyIvaPaid = PurchaseInvoice::whereBetween('invoice_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('tax');

        // 7. IVA F22 (IVA to pay = Collected - Paid)
        $monthlyIvaF22 = $monthlyIva - $monthlyIvaPaid;

        // 8. Total unique transactions
        $totalTransactions = Sale::whereBetween('created_at', [$startDate, $endDate])->count();

        // 9. Daily averages
        $dailySalesData = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(sales.created_at) as sale_date'),
                DB::raw('SUM(sale_items.unit_price * sale_items.quantity) as daily_sales'),
                DB::raw('SUM(sale_items.unit_cost * sale_items.quantity) as daily_cmv'),
                DB::raw('SUM((sale_items.unit_price / ' . self::IVA_RATE . ' - sale_items.unit_cost) * sale_items.quantity) as daily_profit')
            )
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get();

        $dailyProfitAverage = $dailySalesData->avg('daily_profit') ?? 0;
        $dailyCmvAverage = $dailySalesData->avg('daily_cmv') ?? 0;

        // 10. Projections
        $monthlyProfitProjection = $dailyProfitAverage * $daysInRange;
        $monthlyCmvProjection = $dailyCmvAverage * $daysInRange;

        // --- YESTERDAY'S METRICS ---
        $yesterday = now()->subDay()->toDateString();
        $yesterdayLabel = now()->subDay()->format('d/m/Y');

        $yesterdayData = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereDate('sales.created_at', $yesterday)
            ->select(
                DB::raw('SUM(sale_items.unit_price * sale_items.quantity) as sales'),
                DB::raw('SUM(sale_items.unit_cost * sale_items.quantity) as cmv'),
                DB::raw('SUM((sale_items.unit_price / ' . self::IVA_RATE . ' - sale_items.unit_cost) * sale_items.quantity) as profit')
            )
            ->first();

        // --- CHART DATA: Daily trend ---
        $chartData = [];
        $period = $startDate->copy()->daysUntil($endDate);
        $dailyDataMap = $dailySalesData->keyBy('sale_date');

        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $dayData = $dailyDataMap->get($key);
            $chartData[] = [
                'date' => $key,
                'sales' => (float) ($dayData?->daily_sales ?? 0),
                'profit' => (float) ($dayData?->daily_profit ?? 0),
            ];
        }

        // --- SALES BREAKDOWN TABLE ---
        $salesBreakdown = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->select(
                'sales.receipt_number',
                'sales.payment_method',
                'sales.created_at',
                'products.name as product_name',
                'sale_items.quantity',
                DB::raw('sale_items.unit_price * sale_items.quantity as sale_bruto'),
                DB::raw('(sale_items.unit_price * sale_items.quantity) / ' . self::IVA_RATE . ' as sale_neto'),
                DB::raw('(sale_items.unit_price * sale_items.quantity) - (sale_items.unit_price * sale_items.quantity) / ' . self::IVA_RATE . ' as sale_iva'),
                DB::raw('sale_items.unit_cost * sale_items.quantity as cmv'),
                DB::raw('((sale_items.unit_price / ' . self::IVA_RATE . ') - sale_items.unit_cost) * sale_items.quantity as profit')
            )
            ->orderByDesc('sales.created_at')
            ->limit(100)
            ->get();

        // --- MONTH OPTIONS (last 12 months) ---
        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $date = now()->subMonths($i);
            $monthOptions[] = [
                'value' => $date->format('Y-m'),
                'label' => ucfirst($date->translatedFormat('F Y')),
            ];
        }

        return Inertia::render('Tenant/Capital/Index', [
            'kpis' => [
                'monthly_sales' => round($monthlySales, 0),
                'monthly_sales_net' => round($monthlySalesNet, 0),
                'monthly_iva' => round($monthlyIva, 0),
                'monthly_iva_paid' => round($monthlyIvaPaid, 0),
                'monthly_iva_f22' => round($monthlyIvaF22, 0),
                'monthly_cmv' => round($monthlyCmv, 0),
                'monthly_profit' => round($monthlyProfit, 0),
                'monthly_profit_projection' => round($monthlyProfitProjection, 0),
                'monthly_cmv_projection' => round($monthlyCmvProjection, 0),
                'total_transactions' => $totalTransactions,
                'days_in_range' => $daysInRange,
            ],
            'yesterday' => [
                'label' => $yesterdayLabel,
                'sales' => round((float) ($yesterdayData?->sales ?? 0), 0),
                'cmv' => round((float) ($yesterdayData?->cmv ?? 0), 0),
                'profit' => round((float) ($yesterdayData?->profit ?? 0), 0),
            ],
            'chartData' => $chartData,
            'salesBreakdown' => $salesBreakdown,
            'filters' => [
                'month' => $month,
                'start_date' => $isCustomRange ? $startDate->format('Y-m-d') : null,
                'end_date' => $isCustomRange ? $endDate->format('Y-m-d') : null,
                'is_custom_range' => $isCustomRange,
            ],
            'monthOptions' => $monthOptions,
        ]);
    }

    /**
     * Display Capital Analysis by Supplier.
     */
    public function bySupplier(Request $request): Response
    {
        // Date range handling (same logic as index)
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $month = $request->query('month');

        $isCustomRange = false;

        if ($startDate && $endDate) {
            $isCustomRange = true;
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
            $month = null;
        } elseif ($month) {
            $startDate = Carbon::parse($month . '-01')->startOfMonth();
            $endDate = Carbon::parse($month . '-01')->endOfMonth();
        } else {
            $month = now()->format('Y-m');
            $startDate = now()->startOfMonth();
            $endDate = now()->endOfMonth();
        }

        $daysInRange = $startDate->diffInDays($endDate) + 1;

        // --- SUPPLIER METRICS FROM SALES ---
        $supplierMetrics = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('suppliers', 'products.supplier_id', '=', 'suppliers.id')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->select(
                'suppliers.id as supplier_id',
                'suppliers.name as supplier_name',
                DB::raw('SUM(sale_items.unit_price * sale_items.quantity) as total_sales_bruto'),
                DB::raw('SUM((sale_items.unit_price * sale_items.quantity) / ' . self::IVA_RATE . ') as total_sales_neto'),
                DB::raw('SUM(sale_items.unit_cost * sale_items.quantity) as total_cmv'),
                DB::raw('SUM(((sale_items.unit_price / ' . self::IVA_RATE . ') - sale_items.unit_cost) * sale_items.quantity) as total_net_profit')
            )
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_net_profit')
            ->get();

        // --- PURCHASE IVA BY SUPPLIER ---
        $purchaseIvas = PurchaseInvoice::whereBetween('invoice_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->select('supplier_id', DB::raw('SUM(tax) as total_purchase_iva'))
            ->groupBy('supplier_id')
            ->pluck('total_purchase_iva', 'supplier_id')
            ->toArray();

        // --- CONSOLIDATE DATA ---
        $overallTotalSalesBruto = 0;
        $overallTotalSalesNeto = 0;
        $overallTotalCmv = 0;
        $overallTotalNetProfit = 0;
        $overallTotalIvaRecaudado = 0;
        $overallTotalPurchaseIva = 0;
        $countSuppliersWithCredit = 0;
        $countSuppliersToPay = 0;
        $totalCreditFiscalRemanente = 0;

        $consolidatedMetrics = [];
        $chartData = [];

        foreach ($supplierMetrics as $metric) {
            $supplierId = $metric->supplier_id;

            $totalSalesBruto = (float) $metric->total_sales_bruto;
            $totalSalesNeto = (float) $metric->total_sales_neto;
            $totalCmv = (float) $metric->total_cmv;
            $totalNetProfit = (float) $metric->total_net_profit;

            $totalIvaRecaudado = $totalSalesBruto - $totalSalesNeto;
            $totalPurchaseIva = (float) ($purchaseIvas[$supplierId] ?? 0);
            $totalRealIva = $totalIvaRecaudado - $totalPurchaseIva;

            if ($totalRealIva < 0) {
                $countSuppliersWithCredit++;
                $totalCreditFiscalRemanente += abs($totalRealIva);
            } else {
                $countSuppliersToPay++;
            }

            $overallTotalSalesBruto += $totalSalesBruto;
            $overallTotalSalesNeto += $totalSalesNeto;
            $overallTotalCmv += $totalCmv;
            $overallTotalNetProfit += $totalNetProfit;
            $overallTotalIvaRecaudado += $totalIvaRecaudado;
            $overallTotalPurchaseIva += $totalPurchaseIva;

            $consolidatedMetrics[] = [
                'supplier_id' => $supplierId,
                'supplier_name' => $metric->supplier_name,
                'total_sales_bruto' => round($totalSalesBruto, 0),
                'total_sales_neto' => round($totalSalesNeto, 0),
                'total_cmv' => round($totalCmv, 0),
                'total_net_profit' => round($totalNetProfit, 0),
                'total_iva_recaudado' => round($totalIvaRecaudado, 0),
                'total_purchase_iva' => round($totalPurchaseIva, 0),
                'total_real_iva' => round($totalRealIva, 0),
                'margin_percent' => $totalSalesNeto > 0 ? round(($totalNetProfit / $totalSalesNeto) * 100, 1) : 0,
                'contribution_percent' => 0, // Will be calculated client-side or below
            ];

            if ($totalNetProfit > 0) {
                $chartData[] = [
                    'label' => $metric->supplier_name,
                    'value' => round($totalNetProfit, 0),
                ];
            }
        }

        // Calculate contribution percentage
        foreach ($consolidatedMetrics as &$metric) {
            $metric['contribution_percent'] = $overallTotalNetProfit > 0
                ? round(($metric['total_net_profit'] / $overallTotalNetProfit) * 100, 1)
                : 0;
        }
        unset($metric);

        $overallTotalRealIva = $overallTotalIvaRecaudado - $overallTotalPurchaseIva;
        $ivaTotalFinalAPagar = max(0, $overallTotalRealIva);

        // --- MONTH OPTIONS ---
        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $date = now()->subMonths($i);
            $monthOptions[] = [
                'value' => $date->format('Y-m'),
                'label' => ucfirst($date->translatedFormat('F Y')),
            ];
        }

        return Inertia::render('Tenant/Capital/BySupplier', [
            'kpis' => [
                'total_sales_neto' => round($overallTotalSalesNeto, 0),
                'total_cmv' => round($overallTotalCmv, 0),
                'total_net_profit' => round($overallTotalNetProfit, 0),
                'total_iva_recaudado' => round($overallTotalIvaRecaudado, 0),
                'total_purchase_iva' => round($overallTotalPurchaseIva, 0),
                'total_real_iva' => round($overallTotalRealIva, 0),
                'iva_total_final_a_pagar' => round($ivaTotalFinalAPagar, 0),
                'count_suppliers_with_credit' => $countSuppliersWithCredit,
                'total_credit_fiscal_remanente' => round($totalCreditFiscalRemanente, 0),
                'days_in_range' => $daysInRange,
            ],
            'supplierMetrics' => $consolidatedMetrics,
            'chartData' => $chartData,
            'filters' => [
                'month' => $month,
                'start_date' => $isCustomRange ? $startDate->format('Y-m-d') : null,
                'end_date' => $isCustomRange ? $endDate->format('Y-m-d') : null,
                'is_custom_range' => $isCustomRange,
            ],
            'monthOptions' => $monthOptions,
        ]);
    }
}
