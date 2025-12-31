<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\CurrentTenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display sales history.
     */
    public function index(Request $request): Response
    {
        $query = Sale::with(['user', 'items'])
            ->orderByDesc('created_at');

        // Date range filter
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Payment method filter
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $sales = $query->paginate(20)->withQueryString();

        // Calculate summary stats
        $todaySales = Sale::whereDate('created_at', today())
            ->where('status', 'completed')
            ->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(total), 0) as total,
                COALESCE(SUM(cost_of_goods_sold), 0) as cost
            ')
            ->first();

        return Inertia::render('Tenant/Sales/Index', [
            'sales' => $sales,
            'filters' => $request->only(['from', 'to', 'status', 'payment_method']),
            'summary' => [
                'today_count' => $todaySales->count ?? 0,
                'today_total' => (float) ($todaySales->total ?? 0),
                'today_profit' => (float) ($todaySales->total ?? 0) - (float) ($todaySales->cost ?? 0),
            ],
        ]);
    }

    /**
     * Display sale details.
     */
    public function show(Sale $sale): Response
    {
        $sale->load(['user', 'items.product']);

        return Inertia::render('Tenant/Sales/Show', [
            'sale' => $sale,
        ]);
    }
}
