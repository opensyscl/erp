<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Supplier;
use App\Services\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display list of purchase invoices.
     */
    public function index(Request $request): Response
    {
        $query = PurchaseInvoice::with(['supplier', 'createdBy'])
            ->orderByDesc('invoice_date');

        // Filters
        if ($request->filled('supplier')) {
            $query->where('supplier_id', $request->supplier);
        }

        if ($request->filled('status')) {
            if ($request->status === 'paid') {
                $query->paid();
            } elseif ($request->status === 'unpaid') {
                $query->unpaid();
            }
        }

        if ($request->filled('search')) {
            $query->where('invoice_number', 'like', '%' . $request->search . '%');
        }

        $invoices = $query->paginate(20)->withQueryString();

        $suppliers = Supplier::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Tenant/Purchases/Index', [
            'invoices' => $invoices,
            'suppliers' => $suppliers,
            'filters' => $request->only(['supplier', 'status', 'search']),
        ]);
    }

    /**
     * Show create invoice form.
     */
    public function create(): Response
    {
        $suppliers = Supplier::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $products = Product::active()
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'cost', 'price', 'stock']);

        return Inertia::render('Tenant/Purchases/Create', [
            'suppliers' => $suppliers,
            'products' => $products,
        ]);
    }

    /**
     * Store new purchase invoice.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_number' => 'required|string|max:100',
            'invoice_date' => 'required|date',
            'received_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.new_cost' => 'required|numeric|min:0',
            'items.*.margin_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.calculated_sale_price' => 'nullable|numeric|min:0',
            'items.*.update_product_cost' => 'boolean',
            'items.*.update_product_price' => 'boolean',
        ]);

        try {
            DB::transaction(function () use ($validated, $request) {
                // Calculate totals
                $subtotal = 0;
                foreach ($validated['items'] as $item) {
                    $subtotal += $item['quantity'] * $item['new_cost'];
                }

                // Create invoice
                $invoice = PurchaseInvoice::create([
                    'tenant_id' => $this->currentTenant->id(),
                    'supplier_id' => $validated['supplier_id'],
                    'created_by' => $request->user()->id,
                    'invoice_number' => $validated['invoice_number'],
                    'invoice_date' => $validated['invoice_date'],
                    'received_date' => $validated['received_date'] ?? $validated['invoice_date'],
                    'subtotal' => $subtotal,
                    'tax' => round($subtotal * 0.19, 2), // 19% IVA
                    'total_amount' => round($subtotal * 1.19, 2),
                    'status' => 'received',
                    'notes' => $validated['notes'],
                ]);

                // Create items and update products
                foreach ($validated['items'] as $itemData) {
                    $product = Product::find($itemData['product_id']);

                    $item = $invoice->items()->create([
                        'product_id' => $itemData['product_id'],
                        'quantity' => $itemData['quantity'],
                        'previous_cost' => $product->cost,
                        'new_cost' => $itemData['new_cost'],
                        'margin_percentage' => $itemData['margin_percentage'] ?? null,
                        'calculated_sale_price' => $itemData['calculated_sale_price'] ?? null,
                        'subtotal' => $itemData['quantity'] * $itemData['new_cost'],
                        'update_product_cost' => $itemData['update_product_cost'] ?? true,
                        'update_product_price' => $itemData['update_product_price'] ?? false,
                    ]);

                    // Apply updates to product
                    $item->applyToProduct();
                }
            });

            return redirect()->route('purchases.index')
                ->with('success', 'Factura de compra registrada exitosamente.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Error al registrar la factura: ' . $e->getMessage());
        }
    }

    /**
     * Display invoice details.
     */
    public function show(PurchaseInvoice $purchase): Response
    {
        $purchase->load(['supplier', 'createdBy', 'items.product']);

        return Inertia::render('Tenant/Purchases/Show', [
            'invoice' => $purchase,
        ]);
    }

    /**
     * Mark invoice as paid.
     */
    public function markAsPaid(Request $request, PurchaseInvoice $purchase): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => 'nullable|string|max:50',
        ]);

        $purchase->markAsPaid($validated['payment_method'] ?? null);

        return back()->with('success', 'Factura marcada como pagada.');
    }

    /**
     * Delete invoice (only if not paid).
     */
    public function destroy(PurchaseInvoice $purchase): RedirectResponse
    {
        if ($purchase->is_paid) {
            return back()->with('error', 'No se puede eliminar una factura pagada.');
        }

        // Revert stock changes
        foreach ($purchase->items as $item) {
            $product = $item->product;
            $product->stock -= (float) $item->quantity;
            $product->save();
        }

        $purchase->delete();

        return redirect()->route('purchases.index')
            ->with('success', 'Factura eliminada y stock revertido.');
    }
}
