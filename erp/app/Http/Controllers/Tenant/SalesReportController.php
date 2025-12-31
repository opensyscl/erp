<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class SalesReportController extends Controller
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display the Sales Report dashboard.
     */
    public function index(Request $request): Response
    {
        // Date range handling
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $month = $request->query('month', now()->format('Y-m'));

        $isCustomRange = false;

        if ($startDate && $endDate) {
            // Custom date range
            $isCustomRange = true;
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
        } else {
            // Month-based filter
            $startDate = Carbon::parse($month . '-01')->startOfMonth();
            $endDate = Carbon::parse($month . '-01')->endOfMonth();
        }

        // For calculations, if current month and not custom range, use today as end
        $isCurrentMonth = Carbon::parse($month . '-01')->isSameMonth(now()) && !$isCustomRange;
        $endDateForCalc = $isCurrentMonth ? now()->endOfDay() : $endDate;
        $dailySaleDate = $isCurrentMonth ? now()->format('Y-m-d') : $endDate->format('Y-m-d');

        // --- KPIs ---

        // Daily Sales (for the reference date)
        $dailySales = Sale::whereDate('created_at', $dailySaleDate)->sum('total');

        // Accumulated Sales (in range)
        $accumulatedSales = Sale::whereBetween('created_at', [$startDate, $endDateForCalc])->sum('total');

        // Daily Average
        $dailyAverage = Sale::whereBetween('created_at', [$startDate, $endDateForCalc])
            ->selectRaw('DATE(created_at) as sale_date, SUM(total) as daily_total')
            ->groupBy('sale_date')
            ->get()
            ->avg('daily_total') ?? 0;

        // Monthly Projection (only for month filter, not custom range)
        $monthlyProjection = 0;
        if (!$isCustomRange && $dailyAverage > 0) {
            $daysInMonth = Carbon::parse($month . '-01')->daysInMonth;
            $monthlyProjection = $dailyAverage * $daysInMonth;
        }

        // --- Sales Data for Table and Chart ---
        $salesData = Sale::with('user')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($sale) => [
                'id' => $sale->id,
                'receipt_number' => $sale->receipt_number,
                'total' => (float) $sale->total,
                'paid' => (float) $sale->paid,
                'change' => (float) $sale->change,
                'payment_method' => $sale->payment_method,
                'status' => $sale->status ?? 'completed',
                'created_at' => $sale->created_at->format('Y-m-d H:i:s'),
                'sale_date' => $sale->created_at->format('Y-m-d'),
            ]);

        // --- Chart Data: Daily Sales Aggregated ---
        $chartData = Sale::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total', 'date')
            ->toArray();

        // Fill in missing days with 0
        $period = Carbon::parse($startDate)->daysUntil($endDate);
        $filledChartData = [];
        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $filledChartData[$key] = (float) ($chartData[$key] ?? 0);
        }

        // --- Month Options (last 12 months) ---
        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $date = now()->subMonths($i);
            $monthOptions[] = [
                'value' => $date->format('Y-m'),
                'label' => ucfirst($date->translatedFormat('F Y')),
            ];
        }

        return Inertia::render('Tenant/Sales/Index', [
            'kpis' => [
                'daily_sales' => (float) $dailySales,
                'daily_average' => (float) $dailyAverage,
                'accumulated_sales' => (float) $accumulatedSales,
                'monthly_projection' => (float) $monthlyProjection,
                'daily_sale_date' => $dailySaleDate,
            ],
            'salesData' => $salesData,
            'chartData' => $filledChartData,
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
