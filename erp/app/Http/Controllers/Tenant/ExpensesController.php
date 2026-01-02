<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\OperationalExpense;
use App\Services\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExpensesController extends Controller
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display operational expenses with KPIs.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant->get();
        $selectedMonth = $request->get('month', date('Y-m'));

        // Validate month format
        if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $selectedMonth = date('Y-m');
        }

        $monthStart = date('Y-m-01', strtotime($selectedMonth));
        $monthEnd = date('Y-m-t', strtotime($selectedMonth));
        $prevMonthStart = date('Y-m-01', strtotime("$selectedMonth -1 month"));
        $prevMonthEnd = date('Y-m-t', strtotime("$selectedMonth -1 month"));

        // Get KPIs
        $kpis = [
            'total_month' => OperationalExpense::where('tenant_id', $tenant->id)
                ->whereBetween('date_paid', [$monthStart, $monthEnd])
                ->sum('total_amount'),
            'total_fixed' => OperationalExpense::where('tenant_id', $tenant->id)
                ->where('expense_type', 'Fijo')
                ->whereBetween('date_paid', [$monthStart, $monthEnd])
                ->sum('total_amount'),
            'total_variable' => OperationalExpense::where('tenant_id', $tenant->id)
                ->where('expense_type', 'Variable')
                ->whereBetween('date_paid', [$monthStart, $monthEnd])
                ->sum('total_amount'),
            'light' => OperationalExpense::where('tenant_id', $tenant->id)
                ->whereBetween('date_paid', [$monthStart, $monthEnd])
                ->sum('light'),
            'water' => OperationalExpense::where('tenant_id', $tenant->id)
                ->whereBetween('date_paid', [$monthStart, $monthEnd])
                ->sum('water'),
            'rent' => OperationalExpense::where('tenant_id', $tenant->id)
                ->whereBetween('date_paid', [$monthStart, $monthEnd])
                ->sum('rent'),
            'iva_credit' => OperationalExpense::where('tenant_id', $tenant->id)
                ->whereBetween('date_paid', [$monthStart, $monthEnd])
                ->sum('iva'),
            'iva_credit_previous' => OperationalExpense::where('tenant_id', $tenant->id)
                ->whereBetween('date_paid', [$prevMonthStart, $prevMonthEnd])
                ->sum('iva'),
        ];

        // Get expenses for selected month
        $expenses = OperationalExpense::where('tenant_id', $tenant->id)
            ->whereBetween('date_paid', [$monthStart, $monthEnd])
            ->orderByDesc('date_paid')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        // Generate month options (last 12 months)
        $monthOptions = [];
        $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        for ($i = 0; $i < 12; $i++) {
            $date = strtotime("-$i month");
            $ym = date('Y-m', $date);
            $monthOptions[] = [
                'value' => $ym,
                'label' => $months[(int)date('n', $date) - 1] . ' ' . date('Y', $date),
            ];
        }

        return Inertia::render('Tenant/Expenses/Index', [
            'kpis' => $kpis,
            'expenses' => $expenses,
            'selectedMonth' => $selectedMonth,
            'monthOptions' => $monthOptions,
        ]);
    }

    /**
     * Store a new operational expense.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date_paid' => ['required', 'date'],
            'expense_type' => ['required', 'in:Fijo,Variable'],
            'description' => ['required', 'string', 'max:255'],
            'light' => ['numeric', 'min:0'],
            'water' => ['numeric', 'min:0'],
            'rent' => ['numeric', 'min:0'],
            'alarm' => ['numeric', 'min:0'],
            'internet' => ['numeric', 'min:0'],
            'iva' => ['numeric', 'min:0'],
            'repairs' => ['numeric', 'min:0'],
            'supplies' => ['numeric', 'min:0'],
            'other' => ['numeric', 'min:0'],
        ]);

        $tenant = $this->currentTenant->get();

        // Calculate total
        $total = ($validated['light'] ?? 0) + ($validated['water'] ?? 0) +
                 ($validated['rent'] ?? 0) + ($validated['alarm'] ?? 0) +
                 ($validated['internet'] ?? 0) + ($validated['iva'] ?? 0) +
                 ($validated['repairs'] ?? 0) + ($validated['supplies'] ?? 0) +
                 ($validated['other'] ?? 0);

        OperationalExpense::create([
            'tenant_id' => $tenant->id,
            'date_paid' => $validated['date_paid'],
            'expense_type' => $validated['expense_type'],
            'description' => $validated['description'],
            'total_amount' => $total,
            'light' => $validated['light'] ?? 0,
            'water' => $validated['water'] ?? 0,
            'rent' => $validated['rent'] ?? 0,
            'alarm' => $validated['alarm'] ?? 0,
            'internet' => $validated['internet'] ?? 0,
            'iva' => $validated['iva'] ?? 0,
            'repairs' => $validated['repairs'] ?? 0,
            'supplies' => $validated['supplies'] ?? 0,
            'other' => $validated['other'] ?? 0,
        ]);

        $month = date('Y-m', strtotime($validated['date_paid']));

        return back()->with('success', 'Gasto operacional registrado. Total: $' . number_format($total, 0, ',', '.'));
    }
}
