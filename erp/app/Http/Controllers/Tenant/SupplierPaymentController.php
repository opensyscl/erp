<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\PurchaseInvoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SupplierPaymentController extends Controller
{
    /**
     * Display the payments dashboard.
     */
    public function index(Request $request)
    {
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $status = $request->input('status', 'all'); // all, paid, pending

        $query = PurchaseInvoice::query()->with('supplier');

        // Apply Date Filters
        if ($startDate && $endDate) {
            $query->whereBetween('invoice_date', [$startDate, $endDate]);
        } elseif ($month) {
            $startOfMonth = Carbon::parse($month)->startOfMonth();
            $endOfMonth = Carbon::parse($month)->endOfMonth();
            $query->whereBetween('invoice_date', [$startOfMonth, $endOfMonth]);
        }

        // Apply Status Filter
        if ($status === 'paid') {
            $query->where('is_paid', true);
        } elseif ($status === 'pending') {
            $query->where('is_paid', false);
        }

        // Calculate KPIs (based on current date filters, ignoring pagination and status filter for context)
        $kpiQuery = PurchaseInvoice::query();
        if ($startDate && $endDate) {
            $kpiQuery->whereBetween('invoice_date', [$startDate, $endDate]);
        } elseif ($month) {
            $startOfMonth = Carbon::parse($month)->startOfMonth();
            $endOfMonth = Carbon::parse($month)->endOfMonth();
            $kpiQuery->whereBetween('invoice_date', [$startOfMonth, $endOfMonth]);
        }

        // Clone query for efficiency or run aggregates
        // We can do a single aggregate query
        $kpis = $kpiQuery->selectRaw('
            COALESCE(SUM(total_amount), 0) as total_invoices_amount,
            COALESCE(SUM(CASE WHEN is_paid = 1 THEN total_amount ELSE 0 END), 0) as paid_amount,
            COALESCE(SUM(CASE WHEN is_paid = 0 THEN total_amount ELSE 0 END), 0) as pending_amount,
            COUNT(*) as total_count,
            COUNT(CASE WHEN is_paid = 1 THEN 1 END) as paid_count,
            COUNT(CASE WHEN is_paid = 0 THEN 1 END) as pending_count
        ')->first();

        // Get paginated results
        $invoices = $query->orderBy('invoice_date', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->paginate(15)
                          ->withQueryString();

        // Month options
        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $date = Carbon::now()->subMonths($i);
            $monthOptions[] = [
                'value' => $date->format('Y-m'),
                'label' => ucfirst($date->translatedFormat('F Y')),
            ];
        }

        return Inertia::render('Tenant/Payments/Index', [
            'kpis' => $kpis,
            'invoices' => $invoices,
            'filters' => [
                'month' => $month,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
            ],
            'monthOptions' => $monthOptions,
        ]);
    }

    /**
     * Mark invoice as paid.
     */
    public function markAsPaid(Request $request, $id)
    {
        $request->validate([
            'payment_method' => 'required|string|in:Efectivo,Transferencia,Cheque,Tarjeta',
        ]);

        $invoice = PurchaseInvoice::findOrFail($id);

        if ($invoice->is_paid) {
            return back()->with('error', 'La factura ya está pagada.');
        }

        $invoice->update([
            'is_paid' => true,
            'payment_date' => Carbon::now(),
            'payment_method' => $request->payment_method,
            'status' => 'paid',
        ]);

        return back()->with('success', 'Factura marcada como pagada.');
    }

    /**
     * Undo payment (mark as unpaid).
     */
    public function undoPayment($id)
    {
        $invoice = PurchaseInvoice::findOrFail($id);

        if (!$invoice->is_paid) {
            return back()->with('error', 'La factura no está pagada.');
        }

        $invoice->update([
            'is_paid' => false,
            'payment_date' => null,
            'payment_method' => null,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Pago deshecho correctamente.');
    }

    /**
     * Update invoice total amount.
     */
    public function updateAmount(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $invoice = PurchaseInvoice::findOrFail($id);

        $invoice->update([
            'total_amount' => $request->amount,
        ]);

        return back()->with('success', 'Monto actualizado correctamente.');
    }
}
