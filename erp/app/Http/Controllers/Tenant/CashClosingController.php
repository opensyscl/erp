<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CashClosing;
use App\Services\CurrentTenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class CashClosingController extends Controller
{
    /**
     * Display the dashboard and history.
     */
    public function index(Request $request)
    {
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // KPIs always for the selected month to show context
        $startOfMonth = Carbon::parse($month)->startOfMonth();
        $endOfMonth = Carbon::parse($month)->endOfMonth();

        // Calculate Monthly KPIs
        $monthlyKpis = [
            'total_cash' => CashClosing::whereBetween('closing_date', [$startOfMonth, $endOfMonth])->sum('ending_cash'),
            'total_pos1' => CashClosing::whereBetween('closing_date', [$startOfMonth, $endOfMonth])->sum('pos1_sales'),
            'total_pos2' => CashClosing::whereBetween('closing_date', [$startOfMonth, $endOfMonth])->sum('pos2_sales'),
            'total_meli' => CashClosing::whereBetween('closing_date', [$startOfMonth, $endOfMonth])->sum('deposit_meli'),
            'total_bchile' => CashClosing::whereBetween('closing_date', [$startOfMonth, $endOfMonth])->sum('deposit_bchile'),
            'total_bsantander' => CashClosing::whereBetween('closing_date', [$startOfMonth, $endOfMonth])->sum('deposit_bsantander'),
            'total_other' => CashClosing::whereBetween('closing_date', [$startOfMonth, $endOfMonth])->sum('other_outgoings'),
        ];

        // Fetch recent closings for the table
        $query = CashClosing::query()->orderBy('closing_date', 'desc')->orderBy('id', 'desc');

        if ($startDate && $endDate) {
            $query->whereBetween('closing_date', [$startDate, $endDate]);
        } elseif ($month) {
             $query->whereBetween('closing_date', [$startOfMonth, $endOfMonth]);
        }

        $closings = $query->paginate(15)->withQueryString();

        // Month options for filter (last 12 months)
        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $date = Carbon::now()->subMonths($i);
            $monthOptions[] = [
                'value' => $date->format('Y-m'),
                'label' => ucfirst($date->translatedFormat('F Y')),
            ];
        }

        return Inertia::render('Tenant/CashClosings/Index', [
            'kpis' => $monthlyKpis,
            'closings' => $closings,
            'filters' => [
                'month' => $month,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'monthOptions' => $monthOptions,
        ]);
    }

    /**
     * Store a new cash closing.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'starting_cash' => 'required|numeric|min:0',
            'ending_cash' => 'required|numeric|min:0',
            'pos1_sales' => 'required|numeric|min:0',
            'pos2_sales' => 'required|numeric|min:0',
            'deposit_meli' => 'nullable|numeric|min:0',
            'deposit_bchile' => 'nullable|numeric|min:0',
            'deposit_bsantander' => 'nullable|numeric|min:0',
            'other_outgoings' => 'nullable|numeric|min:0',
        ]);

        // Default nulls to 0
        $startingCash = $validated['starting_cash'];
        $endingCash = $validated['ending_cash'];
        $pos1Sales = $validated['pos1_sales'];
        $pos2Sales = $validated['pos2_sales'];
        $depositMeli = $validated['deposit_meli'] ?? 0;
        $depositBchile = $validated['deposit_bchile'] ?? 0;
        $depositBsantander = $validated['deposit_bsantander'] ?? 0;
        $otherOutgoings = $validated['other_outgoings'] ?? 0;

        // Backend Calculations
        $totalOutgoings = $depositMeli + $depositBchile + $depositBsantander + $otherOutgoings;
        $totalDayCash = $pos1Sales + $pos2Sales;

        // (Ending - Starting) + Sales
        // Note: Legacy logic: ($ending_cash - $starting_cash) + $pos1_sales + $pos2_sales;
        // This logic calculates the "Net Flow" or "Expected Income" based on cash diff.
        $totalDayIncome = ($endingCash - $startingCash) + $totalDayCash;

        $incomePlusOutgoings = $totalDayIncome + $totalOutgoings;

        CashClosing::create([
            'closing_date' => Carbon::now()->toDateString(),
            'starting_cash' => $startingCash,
            'ending_cash' => $endingCash,
            'pos1_sales' => $pos1Sales,
            'pos2_sales' => $pos2Sales,
            'total_day_cash' => $totalDayCash,
            'deposit_meli' => $depositMeli,
            'deposit_bchile' => $depositBchile,
            'deposit_bsantander' => $depositBsantander,
            'other_outgoings' => $otherOutgoings,
            'total_outgoings' => $totalOutgoings,
            'total_day_income' => $totalDayIncome,
            'income_plus_outgoings' => $incomePlusOutgoings,
        ]);

        return redirect()->back()->with('success', 'Cierre de caja guardado exitosamente.');
    }
}
